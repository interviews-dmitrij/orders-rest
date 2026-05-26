<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

final class DuplicateOrderException extends RuntimeException implements ApiProblemInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly string $orderId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf('An order with partnerId=%s and orderId=%s already exists.', $partnerId, $orderId),
            previous: $previous,
        );
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Duplicate Order';
    }
}
