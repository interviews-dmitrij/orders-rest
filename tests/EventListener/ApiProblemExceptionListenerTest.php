<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ApiProblemExceptionListener;
use App\Exception\DuplicateOrderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class ApiProblemExceptionListenerTest extends TestCase
{
    private ApiProblemExceptionListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ApiProblemExceptionListener();
    }

    public function testDomainProblemExceptionDictatesStatusTitleAndDetail(): void
    {
        $response = $this->dispatch(new DuplicateOrderException('PARTNER_A', 'ORD-001'), '/api/v1/partners/PARTNER_A/orders');
        $body = self::decode($response);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertArrayNotHasKey('type', $body, 'RFC 7807 type field is intentionally omitted');
        self::assertSame('Duplicate Order', $body['title']);
        self::assertSame(409, $body['status']);
        self::assertSame('/api/v1/partners/PARTNER_A/orders', $body['instance']);
        self::assertStringContainsString('already exists', self::asString($body['detail']));
    }

    public function testValidationFailureSurfacesPerFieldErrorsAtFourTwoTwo(): void
    {
        $exception = new ValidationFailedException('dto', new ConstraintViolationList([
            self::violation('This value should not be blank.', 'orderId'),
            self::violation('This collection should contain 1 element or more.', 'products'),
        ]));

        $body = self::decode($this->dispatch($exception, '/api/v1/partners/PARTNER_A/orders'));

        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(422, $body['status']);
        self::assertSame(
            [
                ['pointer' => '/orderId', 'message' => 'This value should not be blank.'],
                ['pointer' => '/products', 'message' => 'This collection should contain 1 element or more.'],
            ],
            $body['errors'],
        );
    }

    /**
     * `RequestPayloadValueResolver` wraps the original `ValidationFailedException` inside an
     * `HttpException(422, …, previous: $validation)`. The listener must dig into `previous`,
     * otherwise the wrapped case degrades to a generic 422 with no `errors[]` and integration
     * tests against `MapRequestPayload` fail mysteriously.
     */
    public function testUnwrapsValidationFailureFromMapRequestPayloadHttpException(): void
    {
        $inner = new ValidationFailedException('dto', new ConstraintViolationList([
            self::violation('This value should not be blank.', 'orderId'),
        ]));
        $outer = new HttpException(422, 'Validation failed', $inner);

        $body = self::decode($this->dispatch($outer, '/api/v1/partners/PARTNER_A/orders'));

        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(
            [['pointer' => '/orderId', 'message' => 'This value should not be blank.']],
            $body['errors'],
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function symfonyPropertyPathToJsonPointerScenarios(): iterable
    {
        yield 'empty path' => ['', ''];
        yield 'top-level field' => ['orderId', '/orderId'];
        yield 'collection index only' => ['products[0]', '/products/0'];
        yield 'field inside indexed element' => ['products[0].quantity', '/products/0/quantity'];
        yield 'two-level nesting through an index' => ['users[0].address.street', '/users/0/address/street'];
        yield 'two indices and a field' => ['users[0].addresses[1].street', '/users/0/addresses/1/street'];
    }

    #[DataProvider('symfonyPropertyPathToJsonPointerScenarios')]
    public function testConvertsSymfonyPropertyPathToRfc6901JsonPointer(string $propertyPath, string $expectedPointer): void
    {
        $exception = new ValidationFailedException('dto', new ConstraintViolationList([
            self::violation('msg', $propertyPath),
        ]));

        $body = self::decode($this->dispatch($exception, '/api/v1/x'));

        self::assertSame([['pointer' => $expectedPointer, 'message' => 'msg']], $body['errors']);
    }

    /**
     * @return iterable<string, array{0: HttpExceptionInterface, 1: int, 2: string}>
     */
    public static function httpExceptionScenarios(): iterable
    {
        yield 'malformed body' => [new BadRequestHttpException('malformed json body'), 400, 'Malformed JSON'];
        yield 'route not found' => [new NotFoundHttpException('no route'), 404, 'Not Found'];
        yield 'method not allowed' => [new MethodNotAllowedHttpException(['POST']), 405, 'Method Not Allowed'];
        yield 'unsupported media type' => [new UnsupportedMediaTypeHttpException('not json'), 415, 'Unsupported Media Type'];
    }

    #[DataProvider('httpExceptionScenarios')]
    public function testHttpExceptionMapsToCanonicalTitlePerStatus(
        HttpExceptionInterface $exception,
        int $expectedStatus,
        string $expectedTitle,
    ): void {
        $response = $this->dispatch($exception, '/api/v1/partners/PARTNER_A/orders');
        $body = self::decode($response);

        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame($expectedTitle, $body['title']);
        self::assertSame($expectedStatus, $body['status']);
    }

    public function testForwardsHttpExceptionResponseHeaders(): void
    {
        $response = $this->dispatch(
            new MethodNotAllowedHttpException(['POST']),
            '/api/v1/partners/PARTNER_A/orders',
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'), 'Allow must propagate per RFC 9110 §15.5.6');
    }

    public function testUnknownThrowableDegradesToSafeFiveHundredWithoutLeakingDetail(): void
    {
        $body = self::decode($this->dispatch(
            new RuntimeException('database connection refused at host db.internal'),
            '/api/v1/partners/PARTNER_A/orders',
        ));

        self::assertSame('Internal Server Error', $body['title']);
        self::assertSame(500, $body['status']);
        $detail = self::asString($body['detail']);
        self::assertStringNotContainsString('database', $detail);
        self::assertStringNotContainsString('db.internal', $detail);
    }

    private function dispatch(Throwable $exception, string $uri): JsonResponse
    {
        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create($uri),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        return $response;
    }

    private static function violation(string $message, string $propertyPath): ConstraintViolation
    {
        return new ConstraintViolation(
            message: $message,
            messageTemplate: null,
            parameters: [],
            root: '',
            propertyPath: $propertyPath,
            invalidValue: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(JsonResponse $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private static function asString(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
