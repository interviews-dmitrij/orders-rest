<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_products')]
#[ORM\Index(name: 'idx_order_products_order_id', columns: ['order_id'])]
final class OrderProduct
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $order;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $productId;

    #[ORM\Column(type: 'string', length: 255)]
    public private(set) string $name;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    public private(set) string $price;

    #[ORM\Column(type: 'integer')]
    public private(set) int $quantity;

    public function __construct(
        Order $order,
        string $productId,
        string $name,
        string $price,
        int $quantity,
    ) {
        $this->id = Uuid::v4();
        $this->order = $order;
        $this->productId = $productId;
        $this->name = $name;
        $this->price = $price;
        $this->quantity = $quantity;
    }
}
