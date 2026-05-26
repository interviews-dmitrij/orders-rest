<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Order;
use DateTimeInterface;

final readonly class OrderResponse
{
    /**
     * @param list<OrderProductResponse> $products
     */
    public function __construct(
        public string $partnerId,
        public string $orderId,
        public string $expectedDeliveryDate,
        public string $totalValue,
        public array $products,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        $products = [];
        foreach ($order->products as $product) {
            $products[] = OrderProductResponse::fromEntity($product);
        }

        return new self(
            partnerId: $order->partnerId,
            orderId: $order->orderId,
            expectedDeliveryDate: $order->expectedDeliveryDate->format('Y-m-d'),
            totalValue: (string) $order->totalValue,
            products: $products,
            createdAt: $order->createdAt->format(DateTimeInterface::RFC3339),
            updatedAt: $order->updatedAt->format(DateTimeInterface::RFC3339),
        );
    }
}
