<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

final class OrderNotFoundException extends RuntimeException implements ApiProblemInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly string $orderId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf('No order with partnerId=%s and orderId=%s exists.', $partnerId, $orderId),
            previous: $previous,
        );
    }

    public function httpStatus(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Order Not Found';
    }
}
