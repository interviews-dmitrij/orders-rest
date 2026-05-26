<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\OrderAuditLog;
use App\Enum\OrderAuditEventType;
use App\Event\OrderDeliveryDateChangedEvent;
use App\Repository\OrderAuditLogRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class OrderAuditListener
{
    public function __construct(
        private readonly OrderAuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

    #[AsEventListener]
    public function onDeliveryDateChanged(OrderDeliveryDateChangedEvent $event): void
    {
        $this->auditLogRepository->save(new OrderAuditLog(
            orderId: $event->orderId,
            partnerId: $event->partnerId,
            orderIdValue: $event->orderIdValue,
            eventType: OrderAuditEventType::DeliveryDateChanged,
            changes: [
                'expectedDeliveryDate' => [
                    'old' => $event->previousDeliveryDate->format('Y-m-d'),
                    'new' => $event->newDeliveryDate->format('Y-m-d'),
                ],
            ],
            actorUserId: $event->actorUserId,
            occurredAt: $event->occurredAt,
        ));
    }
}
