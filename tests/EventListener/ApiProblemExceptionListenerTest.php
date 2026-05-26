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
    public function testMapsApiProblemExceptionToRfc7807Response(): void
    {
        $listener = new ApiProblemExceptionListener();
        $exception = new DuplicateOrderException('PARTNER_A', 'ORD-001');
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{title: string, status: int, detail: string, instance: string} $body */
        $body = self::decode($response);
        self::assertArrayNotHasKey('type', $body);
        self::assertSame('Duplicate Order', $body['title']);
        self::assertSame(409, $body['status']);
        self::assertSame('/api/v1/partners/PARTNER_A/orders', $body['instance']);
        self::assertStringContainsString('already exists', $body['detail']);
    }

    public function testMapsValidationFailedToRfc7807ValidationProblem(): void
    {
        $listener = new ApiProblemExceptionListener();
        $violations = new ConstraintViolationList([
            $this->violation('This value should not be blank.', 'orderId'),
            $this->violation('This collection should contain 1 element or more.', 'products'),
            $this->violation('This value should be greater than or equal to 1.', 'products[0].quantity'),
        ]);
        $exception = new ValidationFailedException(value: 'dto', violations: $violations);
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{title: string, status: int, errors: list<array{pointer: string, message: string}>} $body */
        $body = self::decode($response);
        self::assertArrayNotHasKey('type', $body);
        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(422, $body['status']);
        self::assertSame(
            [
                ['pointer' => '/orderId', 'message' => 'This value should not be blank.'],
                ['pointer' => '/products', 'message' => 'This collection should contain 1 element or more.'],
                ['pointer' => '/products/0/quantity', 'message' => 'This value should be greater than or equal to 1.'],
            ],
            $body['errors'],
        );
    }

    public function testMapsWrappedValidationFailureFromMapRequestPayloadResolver(): void
    {
        $inner = new ValidationFailedException(
            value: 'dto',
            violations: new ConstraintViolationList([
                $this->violation('This value should not be blank.', 'orderId'),
            ]),
        );
        $outer = new HttpException(422, 'Validation failed', $inner);
        $listener = new ApiProblemExceptionListener();
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $outer);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());

        /** @var array{title: string, errors: list<array{pointer: string, message: string}>} $body */
        $body = self::decode($response);
        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(
            [['pointer' => '/orderId', 'message' => 'This value should not be blank.']],
            $body['errors'],
        );
    }

    /**
     * @return iterable<string, array{0: HttpExceptionInterface, 1: int, 2: string}>
     */
    public static function httpExceptionScenarios(): iterable
    {
        yield '400 bad-request' => [new BadRequestHttpException('malformed json body'), 400, 'Malformed JSON'];
        yield '404 not-found' => [new NotFoundHttpException('no route'), 404, 'Not Found'];
        yield '405 method-not-allowed' => [new MethodNotAllowedHttpException(['POST']), 405, 'Method Not Allowed'];
        yield '415 unsupported-media-type' => [new UnsupportedMediaTypeHttpException('not json'), 415, 'Unsupported Media Type'];
    }

    #[DataProvider('httpExceptionScenarios')]
    public function testMapsHttpExceptionToRfc7807ProblemByStatus(
        HttpExceptionInterface $exception,
        int $expectedStatus,
        string $expectedTitle,
    ): void {
        $listener = new ApiProblemExceptionListener();
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{title: string, status: int} $body */
        $body = self::decode($response);
        self::assertArrayNotHasKey('type', $body);
        self::assertSame($expectedTitle, $body['title']);
        self::assertSame($expectedStatus, $body['status']);
    }

    public function testMapsUnknownThrowableToInternalServerError(): void
    {
        $listener = new ApiProblemExceptionListener();
        $exception = new RuntimeException('database connection refused');
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{title: string, status: int, detail: string} $body */
        $body = self::decode($response);
        self::assertArrayNotHasKey('type', $body);
        self::assertSame('Internal Server Error', $body['title']);
        self::assertSame(500, $body['status']);
        self::assertStringNotContainsString('database connection refused', $body['detail'], 'must not leak raw exception message');
    }

    public function testForwardsHeadersFromHttpExceptionToProblemResponse(): void
    {
        $listener = new ApiProblemExceptionListener();
        $exception = new MethodNotAllowedHttpException(['POST']);
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'));
    }

    private function event(string $uri, Throwable $exception): ExceptionEvent
    {
        $kernel = self::createStub(HttpKernelInterface::class);

        return new ExceptionEvent(
            $kernel,
            Request::create($uri),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    private function violation(string $message, string $propertyPath): ConstraintViolation
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
}
