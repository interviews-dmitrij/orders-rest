<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ApiProblemInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[AsEventListener(event: 'kernel.exception')]
final class ApiProblemExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $instance = $event->getRequest()->getPathInfo();

        if ($exception instanceof ApiProblemInterface) {
            $event->setResponse($this->fromApiProblem($exception, $instance));

            return;
        }

        $validationFailure = self::extractValidationFailure($exception);
        if (null !== $validationFailure) {
            $event->setResponse($this->fromValidationFailed($validationFailure, $instance));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse($this->fromHttpException($exception, $instance));

            return;
        }

        $event->setResponse($this->problemResponse(
            slug: 'internal-server-error',
            title: 'Internal Server Error',
            status: 500,
            detail: 'An unexpected error occurred.',
            instance: $instance,
        ));
    }

    private function fromApiProblem(ApiProblemInterface $exception, string $instance): JsonResponse
    {
        return $this->problemResponse(
            slug: $exception->problemSlug(),
            title: $exception->title(),
            status: $exception->httpStatus(),
            detail: $exception->getMessage(),
            instance: $instance,
        );
    }

    private function fromValidationFailed(ValidationFailedException $exception, string $instance): JsonResponse
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'pointer' => self::propertyPathToJsonPointer($violation->getPropertyPath()),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->problemResponse(
            slug: 'validation-failed',
            title: 'Validation Failed',
            status: 422,
            detail: 'One or more fields failed validation.',
            instance: $instance,
            extra: ['errors' => $errors],
        );
    }

    private function fromHttpException(HttpExceptionInterface $exception, string $instance): JsonResponse
    {
        $status = $exception->getStatusCode();
        [$slug, $title] = self::slugAndTitleForStatus($status);
        $response = $this->problemResponse(
            slug: $slug,
            title: $title,
            status: $status,
            detail: '' !== $exception->getMessage() ? $exception->getMessage() : $title,
            instance: $instance,
        );
        foreach ($exception->getHeaders() as $name => $value) {
            if (\is_string($name) && \is_string($value)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function problemResponse(
        string $slug,
        string $title,
        int $status,
        string $detail,
        string $instance,
        array $extra = [],
    ): JsonResponse {
        $body = [
            'type' => $slug,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $instance,
            ...$extra,
        ];

        $response = new JsonResponse($body, $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }

    /**
     * Returns the inner ValidationFailedException if `$exception` is one, or
     * wraps one (RequestPayloadValueResolver wraps in HttpException).
     */
    private static function extractValidationFailure(Throwable $exception): ?ValidationFailedException
    {
        if ($exception instanceof ValidationFailedException) {
            return $exception;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            return $previous;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function slugAndTitleForStatus(int $status): array
    {
        return match ($status) {
            400 => ['malformed-json', 'Malformed JSON'],
            404 => ['not-found', 'Not Found'],
            405 => ['method-not-allowed', 'Method Not Allowed'],
            415 => ['unsupported-media-type', 'Unsupported Media Type'],
            422 => ['validation-failed', 'Validation Failed'],
            default => ['http-error', 'HTTP Error'],
        };
    }

    /**
     * Converts a Symfony property path (`products[0].quantity`) into an
     * RFC 6901 JSON Pointer (`/products/0/quantity`).
     */
    private static function propertyPathToJsonPointer(string $path): string
    {
        if ('' === $path) {
            return '';
        }

        $normalized = str_replace(['].', '['], ['/', '/'], $path);
        $normalized = str_replace(['.', ']'], ['/', ''], $normalized);

        return '/' . ltrim($normalized, '/');
    }
}
