<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\OrderDeliveryDateChangedEvent;
use App\EventListener\OrderAuditListener;
use App\Tests\Repository\InMemoryOrderAuditLogRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OrderAuditListenerTest extends TestCase
{
    public function testPersistsAuditRowWithFullChangeSet(): void
    {
        $repository = new InMemoryOrderAuditLogRepository();
        $listener = new OrderAuditListener($repository);
        $orderId = Uuid::v4();
        $event = new OrderDeliveryDateChangedEvent(
            orderId: $orderId,
            partnerId: 'PARTNER_A',
            orderIdValue: 'ORD-001',
            previousDeliveryDate: new DateTimeImmutable('2026-06-15'),
            newDeliveryDate: new DateTimeImmutable('2026-07-20'),
            actorUserId: 'mock-user',
            occurredAt: new DateTimeImmutable('2026-05-26T11:30:00+00:00'),
        );

        $listener->onDeliveryDateChanged($event);

        self::assertCount(1, $repository->entries);
        $log = $repository->entries[0];
        self::assertSame($orderId, $log->orderId);
        self::assertSame('PARTNER_A', $log->partnerId);
        self::assertSame('ORD-001', $log->orderIdValue);
        self::assertSame('delivery_date_changed', $log->eventType);
        self::assertSame(
            ['expectedDeliveryDate' => ['old' => '2026-06-15', 'new' => '2026-07-20']],
            $log->changes,
        );
        self::assertSame('mock-user', $log->actorUserId);
        self::assertEquals($event->occurredAt, $log->occurredAt);
    }
}
