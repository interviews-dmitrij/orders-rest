<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class UpdateOrderDeliveryDateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     */
    public function update(string $partnerId, string $orderId, UpdateOrderDeliveryDateRequest $request): Order
    {
        $order = $this->orderRepository->findByCompositeKey($partnerId, $orderId);
        if (null === $order) {
            throw new OrderNotFoundException($partnerId, $orderId);
        }

        $order->changeExpectedDeliveryDate(new DateTimeImmutable($request->expectedDeliveryDate), $this->clock->now());
        $this->orderRepository->save($order);

        return $order;
    }
}
