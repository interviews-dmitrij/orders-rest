<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Exception\DuplicateOrderException;
use App\Repository\OrderRepositoryInterface;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

final class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
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

        $totalValue = BigDecimal::of($request->totalValue)->toScale(2);

        $order = new Order(
            partnerId: $partnerId,
            orderId: $request->orderId,
            expectedDeliveryDate: $request->expectedDeliveryDate,
            totalValue: $totalValue,
            createdAt: new DateTimeImmutable(),
        );

        foreach ($request->products as $productRequest) {
            $price = BigDecimal::of($productRequest->price)->toScale(2);

            $product = new OrderProduct(
                order: $order,
                productId: $productRequest->productId,
                name: $productRequest->name,
                price: $price,
                quantity: $productRequest->quantity,
            );
            $order->products->add($product);
        }

        $this->orderRepository->save($order);

        return $order;
    }
}
