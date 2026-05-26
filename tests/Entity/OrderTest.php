<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testChangeExpectedDeliveryDateUpdatesDateAndBumpsUpdatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $order = new Order(
            partnerId: 'PARTNER_A',
            orderId: 'ORD-001',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: BigDecimal::of('100.00'),
            createdAt: $createdAt,
        );
        $newDate = new DateTimeImmutable('2026-07-20');
        $occurredAt = new DateTimeImmutable('2026-05-26T11:30:00+00:00');

        $order->changeExpectedDeliveryDate($newDate, $occurredAt);

        self::assertSame($newDate, $order->expectedDeliveryDate);
        self::assertSame($occurredAt, $order->updatedAt);
        self::assertSame($createdAt, $order->createdAt, 'createdAt must remain untouched on update');
    }
}
