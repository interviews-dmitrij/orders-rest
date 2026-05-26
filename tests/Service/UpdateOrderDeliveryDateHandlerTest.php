<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Entity\Order;
use App\Event\OrderDeliveryDateChangedEvent;
use App\Exception\OrderNotFoundException;
use App\Service\UpdateOrderDeliveryDateHandler;
use App\Tests\Repository\InMemoryOrderRepository;
use App\UserContext\MockUserContext;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class UpdateOrderDeliveryDateHandlerTest extends TestCase
{
    private const string CREATED_AT = '2026-05-01T10:00:00+00:00';

    private InMemoryOrderRepository $repository;
    private MockClock $clock;
    private EventDispatcher $eventDispatcher;
    private UpdateOrderDeliveryDateHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-05-26T11:30:00+00:00');
        $this->eventDispatcher = new EventDispatcher();
        $this->handler = new UpdateOrderDeliveryDateHandler(
            $this->repository,
            $this->clock,
            $this->eventDispatcher,
            new MockUserContext(),
        );
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

    public function testDispatchesOrderDeliveryDateChangedEventAfterSuccessfulUpdate(): void
    {
        $seeded = $this->seedOrder();
        $captured = null;
        $this->eventDispatcher->addListener(
            OrderDeliveryDateChangedEvent::class,
            static function (OrderDeliveryDateChangedEvent $event) use (&$captured): void {
                $captured = $event;
            },
        );

        $this->handler->update('PARTNER_A', 'ORD-001', new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20')));

        self::assertInstanceOf(OrderDeliveryDateChangedEvent::class, $captured);
        self::assertSame($seeded->id, $captured->orderId);
        self::assertSame('PARTNER_A', $captured->partnerId);
        self::assertSame('ORD-001', $captured->orderIdValue);
        self::assertSame('2026-06-15', $captured->previousDeliveryDate->format('Y-m-d'));
        self::assertSame('2026-07-20', $captured->newDeliveryDate->format('Y-m-d'));
        self::assertSame(MockUserContext::MOCK_USER_ID, $captured->actorUserId);
        self::assertEquals($this->clock->now(), $captured->occurredAt);
    }

    public function testDoesNotDispatchEventWhenOrderIsMissing(): void
    {
        $captured = null;
        $this->eventDispatcher->addListener(
            OrderDeliveryDateChangedEvent::class,
            static function (OrderDeliveryDateChangedEvent $event) use (&$captured): void {
                $captured = $event;
            },
        );

        try {
            $this->handler->update('PARTNER_A', 'ORD-MISSING', new UpdateOrderDeliveryDateRequest(new DateTimeImmutable('2026-07-20')));
            self::fail('expected OrderNotFoundException');
        } catch (OrderNotFoundException) {
            self::assertNull($captured, 'no event must be dispatched if the lookup fails');
        }
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
