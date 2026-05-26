<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Exception\DuplicateOrderException;
use App\Repository\OrderRepositoryInterface;
use DateTimeImmutable;
use LogicException;

final class OrderCreator
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    /**
     * @throws DuplicateOrderException
     */
    public function create(string $partnerId, CreateOrderRequest $request): Order
    {
        $expectedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $request->expectedDeliveryDate);
        if (false === $expectedDeliveryDate) {
            throw new LogicException(\sprintf(
                'expectedDeliveryDate "%s" passed Assert\\Date but failed parsing.',
                $request->expectedDeliveryDate,
            ));
        }

        $order = new Order(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: $expectedDeliveryDate,
            totalValue: $request->totalValue,
            createdAt: new DateTimeImmutable(),
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

        $this->orders->save($order);

        return $order;
    }
}
