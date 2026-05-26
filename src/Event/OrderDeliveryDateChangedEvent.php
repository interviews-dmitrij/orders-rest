<?php

declare(strict_types=1);

namespace App\Event;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class OrderDeliveryDateChangedEvent
{
    public function __construct(
        public Uuid $orderId,
        public string $partnerId,
        public string $orderIdValue,
        public DateTimeImmutable $previousDeliveryDate,
        public DateTimeImmutable $newDeliveryDate,
        public string $actorUserId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
