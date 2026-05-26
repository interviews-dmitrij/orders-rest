<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ApiProblemExceptionListener;
use App\Exception\DuplicateOrderException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class ApiProblemExceptionListenerTest extends TestCase
{
    private const PROBLEM_BASE = 'https://api.test/problems';

    public function testMapsApiProblemExceptionToRfc7807Response(): void
    {
        // Arrange
        $listener = new ApiProblemExceptionListener(self::PROBLEM_BASE);
        $exception = new DuplicateOrderException('PARTNER_A', 'ORD-001');
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        // Act
        $listener->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, detail: string, instance: string} $body */
        $body = self::decode($response);
        self::assertSame(self::PROBLEM_BASE . '/duplicate-order', $body['type']);
        self::assertSame('Duplicate Order', $body['title']);
        self::assertSame(409, $body['status']);
        self::assertSame('/api/v1/partners/PARTNER_A/orders', $body['instance']);
        self::assertStringContainsString('already exists', $body['detail']);
    }

    public function testMapsValidationFailedToRfc7807ValidationProblem(): void
    {
        // Arrange
        $listener = new ApiProblemExceptionListener(self::PROBLEM_BASE);
        $violations = new ConstraintViolationList([
            $this->violation('This value should not be blank.', 'orderId'),
            $this->violation('This collection should contain 1 element or more.', 'products'),
            $this->violation('This value should be greater than or equal to 1.', 'products[0].quantity'),
        ]);
        $exception = new ValidationFailedException(value: 'dto', violations: $violations);
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        // Act
        $listener->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, errors: list<array{pointer: string, message: string}>} $body */
        $body = self::decode($response);
        self::assertSame(self::PROBLEM_BASE . '/validation-failed', $body['type']);
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
        // Arrange — RequestPayloadValueResolver wraps ValidationFailedException in HttpException(422).
        $inner = new ValidationFailedException(
            value: 'dto',
            violations: new ConstraintViolationList([
                $this->violation('This value should not be blank.', 'orderId'),
            ]),
        );
        $outer = new HttpException(422, 'Validation failed', $inner);
        $listener = new ApiProblemExceptionListener(self::PROBLEM_BASE);
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $outer);

        // Act
        $listener->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(422, $response->getStatusCode());

        /** @var array{type: string, title: string, errors: list<array{pointer: string, message: string}>} $body */
        $body = self::decode($response);
        self::assertSame(self::PROBLEM_BASE . '/validation-failed', $body['type']);
        self::assertSame('Validation Failed', $body['title']);
        self::assertSame(
            [['pointer' => '/orderId', 'message' => 'This value should not be blank.']],
            $body['errors'],
        );
    }

    public function testMapsUnsupportedMediaTypeToRfc7807Problem(): void
    {
        // Arrange
        $listener = new ApiProblemExceptionListener(self::PROBLEM_BASE);
        $exception = new UnsupportedMediaTypeHttpException('text/plain is not supported');
        $event = $this->event('/api/v1/partners/PARTNER_A/orders', $exception);

        // Act
        $listener->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(415, $response->getStatusCode());

        /** @var array{type: string, title: string, status: int, detail: string} $body */
        $body = self::decode($response);
        self::assertSame(self::PROBLEM_BASE . '/unsupported-media-type', $body['type']);
        self::assertSame('Unsupported Media Type', $body['title']);
        self::assertSame(415, $body['status']);
        self::assertStringContainsString('text/plain', $body['detail']);
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
