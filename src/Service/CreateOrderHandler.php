<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Exception\DuplicateOrderException;
use App\Repository\OrderRepositoryInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws DuplicateOrderException
     */
    public function create(string $partnerId, CreateOrderRequest $request): Order
    {
        if (null !== $this->orderRepository->findByCompositeKey($partnerId, $request->orderId)) {
            throw new DuplicateOrderException($partnerId, $request->orderId);
        }

        $order = new Order(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: new DateTimeImmutable($request->expectedDeliveryDate),
            totalValue: $request->totalValue,
            createdAt: $this->clock->now(),
        );

        foreach ($request->products as $productRequest) {
            $product = new OrderProduct(
                order: $order,
                productId: $productRequest->productId,
                name: $productRequest->name,
                price: $productRequest->price,
                quantity: $productRequest->quantity,
            );
            $order->products->add($product);
        }

        $this->orderRepository->save($order);

        return $order;
    }
}
