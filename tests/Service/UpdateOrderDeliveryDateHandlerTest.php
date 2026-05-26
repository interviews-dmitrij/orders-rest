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
use Symfony\Component\Clock\MockClock;

final class UpdateOrderDeliveryDateHandlerTest extends TestCase
{
    private const string CREATED_AT = '2026-05-01T10:00:00+00:00';

    private InMemoryOrderRepository $repository;
    private MockClock $clock;
    private UpdateOrderDeliveryDateHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-05-26T11:30:00+00:00');
        $this->handler = new UpdateOrderDeliveryDateHandler($this->repository, $this->clock);
    }

    public function testReplacesExpectedDeliveryDateAndStampsUpdatedAtFromClock(): void
    {
        $seeded = $this->seedOrder();
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $updated = $this->handler->update('PARTNER_A', 'ORD-001', $request);

        self::assertSame($seeded, $updated);
        self::assertSame('2026-07-20', $updated->expectedDeliveryDate->format('Y-m-d'));
        self::assertEquals($this->clock->now(), $updated->updatedAt);
        self::assertSame($seeded->createdAt, $updated->createdAt, 'createdAt must remain immutable on update');
    }

    public function testRejectsLookupWhenOrderDoesNotExist(): void
    {
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $this->expectException(OrderNotFoundException::class);

        $this->handler->update('PARTNER_A', 'ORD-MISSING', $request);
    }

    /**
     * Partner isolation: the composite key `(partnerId, orderId)` is the only legitimate
     * route to an order. If `findByCompositeKey` ever degraded to single-key lookup the
     * handler would silently mutate another partner's order — this guard catches that.
     */
    public function testRejectsCrossPartnerLookup(): void
    {
        $this->seedOrder();
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $this->expectException(OrderNotFoundException::class);

        $this->handler->update('PARTNER_OTHER', 'ORD-001', $request);
    }

    public function testRepeatedUpdatesAdvanceUpdatedAtWhileConvergingOnTheSameFinalDate(): void
    {
        $this->seedOrder();
        $request = new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20'));

        $first = $this->handler->update('PARTNER_A', 'ORD-001', $request);
        $firstUpdatedAt = $first->updatedAt;

        $this->clock->sleep(60);
        $second = $this->handler->update('PARTNER_A', 'ORD-001', $request);

        self::assertSame('2026-07-20', $second->expectedDeliveryDate->format('Y-m-d'));
        self::assertGreaterThan(
            $firstUpdatedAt,
            $second->updatedAt,
            'each PUT must restamp updatedAt — a no-op fast path would leak through',
        );
    }

    private function seedOrder(): Order
    {
        $order = new Order(
            partnerId: 'PARTNER_A',
            orderId: 'ORD-001',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: BigDecimal::of('100.00'),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
        $this->repository->save($order);

        return $order;
    }
}
