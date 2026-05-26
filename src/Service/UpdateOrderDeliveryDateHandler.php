<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Entity\Order;
use App\Event\OrderDeliveryDateChangedEvent;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepositoryInterface;
use App\UserContext\UserContextInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UpdateOrderDeliveryDateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserContextInterface $userContext,
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

        $previousDeliveryDate = $order->expectedDeliveryDate;
        $occurredAt = $this->clock->now();

        return $this->orderRepository->wrapInTransaction(function () use ($order, $request, $previousDeliveryDate, $occurredAt): Order {
            $order->changeExpectedDeliveryDate($request->expectedDeliveryDate, $occurredAt);
            $this->orderRepository->save($order);

            $this->eventDispatcher->dispatch(new OrderDeliveryDateChangedEvent(
                orderId: $order->id,
                partnerId: $order->partnerId,
                orderIdValue: $order->orderId,
                previousDeliveryDate: $previousDeliveryDate,
                newDeliveryDate: $request->expectedDeliveryDate,
                actorUserId: $this->userContext->userId(),
                occurredAt: $occurredAt,
            ));

            return $order;
        });
    }
}
