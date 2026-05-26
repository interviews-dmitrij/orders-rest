<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctrineOrderRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineOrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'orders_partner_order_unique', columns: ['partner_id', 'order_id'])]
final class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $partnerId;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $orderId;

    #[ORM\Column(type: 'date_immutable')]
    public private(set) DateTimeImmutable $expectedDeliveryDate;

    #[ORM\Column(type: 'bigdecimal', precision: 14, scale: 2)]
    public private(set) BigDecimal $totalValue;

    /** @var Collection<int, OrderProduct> */
    #[ORM\OneToMany(targetEntity: OrderProduct::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public private(set) Collection $products;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public private(set) DateTimeImmutable $updatedAt;

    public function __construct(
        string $partnerId,
        string $orderId,
        DateTimeImmutable $expectedDeliveryDate,
        BigDecimal $totalValue,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v4();
        $this->partnerId = $partnerId;
        $this->orderId = $orderId;
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        $this->totalValue = $totalValue;
        $this->products = new ArrayCollection();
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }
}
