<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
use DateTimeImmutable;

final class UpdateOrderDeliveryDateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
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

        $order->changeExpectedDeliveryDate($request->expectedDeliveryDate, new DateTimeImmutable());
        $this->orderRepository->save($order);

        return $order;
    }
}
