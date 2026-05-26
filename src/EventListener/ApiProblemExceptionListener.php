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
            title: 'Internal Server Error',
            status: 500,
            detail: 'An unexpected error occurred.',
            instance: $instance,
        ));
    }

    private function fromApiProblem(ApiProblemInterface $exception, string $instance): JsonResponse
    {
        return $this->problemResponse(
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
        $title = self::titleForStatus($status);
        $response = $this->problemResponse(
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
        string $title,
        int $status,
        string $detail,
        string $instance,
        array $extra = [],
    ): JsonResponse {
        $body = [
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

    private static function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Malformed JSON',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            415 => 'Unsupported Media Type',
            422 => 'Validation Failed',
            default => 'HTTP Error',
        };
    }

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
