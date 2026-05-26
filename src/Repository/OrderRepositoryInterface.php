<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Exception\DuplicateOrderException;

interface OrderRepositoryInterface
{
    /**
     * @throws DuplicateOrderException when an order with the same `(partnerId, orderId)` already exists
     */
    public function save(Order $order): void;

    public function findByCompositeKey(string $partnerId, string $orderId): ?Order;

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    public function wrapInTransaction(callable $action): mixed;
}
