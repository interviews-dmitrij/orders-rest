<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Order;
use App\Exception\DuplicateOrderException;
use App\Repository\OrderRepositoryInterface;

/**
 * In-memory `OrderRepositoryInterface` implementation for unit tests.
 *
 * Mirrors the Doctrine adapter's contract: `save()` throws on duplicate
 * composite key; `findByCompositeKey()` returns null when missing.
 */
final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var list<array{partnerId: string, orderId: string}> */
    public private(set) array $lookupCalls = [];
    /** @var array<string, Order> */
    private array $orders = [];

    public function save(Order $order): void
    {
        $key = $this->key($order->partnerId, $order->orderId);
        // Mirrors Doctrine UoW: flushing a managed (already-tracked) entity is not a
        // duplicate — it just persists the pending changes. Only reject when a *different*
        // object is stored under the same key (i.e. an unmanaged entity with a colliding key).
        if (isset($this->orders[$key]) && $this->orders[$key] !== $order) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId);
        }
        $this->orders[$key] = $order;
    }

    public function findByCompositeKey(string $partnerId, string $orderId): ?Order
    {
        $this->lookupCalls[] = ['partnerId' => $partnerId, 'orderId' => $orderId];

        return $this->orders[$this->key($partnerId, $orderId)] ?? null;
    }

    public function count(): int
    {
        return \count($this->orders);
    }

    private function key(string $partnerId, string $orderId): string
    {
        return $partnerId . "\x00" . $orderId;
    }
}
