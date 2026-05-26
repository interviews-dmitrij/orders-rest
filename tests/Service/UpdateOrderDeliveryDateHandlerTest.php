<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use App\Service\UpdateOrderDeliveryDateHandler;
use App\Tests\Repository\InMemoryOrderRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UpdateOrderDeliveryDateHandlerTest extends TestCase
{
    private InMemoryOrderRepository $repository;
    private UpdateOrderDeliveryDateHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrderRepository();
        $this->handler = new UpdateOrderDeliveryDateHandler($this->repository);
    }

    public function testReplacesExpectedDeliveryDateAndBumpsUpdatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $order = self::seed($this->repository, $createdAt);
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $updated = $this->handler->update('PARTNER_A', 'ORD-001', $request);

        self::assertSame($order, $updated);
        self::assertSame('2026-07-20', $updated->expectedDeliveryDate->format('Y-m-d'));
        self::assertGreaterThan($createdAt, $updated->updatedAt, 'updatedAt must move forward');
        self::assertSame($createdAt, $updated->createdAt);
    }

    public function testRejectsLookupWhenOrderDoesNotExist(): void
    {
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $this->expectException(OrderNotFoundException::class);

        $this->handler->update('PARTNER_A', 'ORD-MISSING', $request);
    }

    public function testRepeatedUpdatesConvergeOnTheSameFinalDate(): void
    {
        self::seed($this->repository, new DateTimeImmutable('2026-05-01T10:00:00+00:00'));
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $this->handler->update('PARTNER_A', 'ORD-001', $request);
        $second = $this->handler->update('PARTNER_A', 'ORD-001', $request);

        self::assertSame('2026-07-20', $second->expectedDeliveryDate->format('Y-m-d'));
    }

    private static function seed(InMemoryOrderRepository $repository, DateTimeImmutable $createdAt): Order
    {
        $order = new Order(
            partnerId: 'PARTNER_A',
            orderId: 'ORD-001',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: BigDecimal::of('100.00'),
            createdAt: $createdAt,
        );
        $repository->save($order);

        return $order;
    }
}
