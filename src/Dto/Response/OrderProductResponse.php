<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\OrderProduct;

final readonly class OrderProductResponse
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $price,
        public int $quantity,
    ) {
    }

    public static function fromEntity(OrderProduct $product): self
    {
        return new self(
            productId: $product->productId,
            name: $product->name,
            price: (string) $product->price,
            quantity: $product->quantity,
        );
    }
}
