<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderAuditEventType;
use App\Repository\DoctrineOrderAuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineOrderAuditLogRepository::class)]
#[ORM\Table(name: 'order_audit_log')]
#[ORM\Index(name: 'idx_order_audit_log_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_order_audit_log_partner_order', columns: ['partner_id', 'order_id_value'])]
final class OrderAuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $orderId;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $partnerId;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $orderIdValue;

    #[ORM\Column(type: 'string', length: 64, enumType: OrderAuditEventType::class)]
    public private(set) OrderAuditEventType $eventType;

    /** @var array<string, array{old: mixed, new: mixed}> */
    #[ORM\Column(type: 'json')]
    public private(set) array $changes;

    #[ORM\Column(type: 'string', length: 64)]
    public private(set) string $actorUserId;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public private(set) DateTimeImmutable $occurredAt;

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        Uuid $orderId,
        string $partnerId,
        string $orderIdValue,
        OrderAuditEventType $eventType,
        array $changes,
        string $actorUserId,
        DateTimeImmutable $occurredAt,
    ) {
        $this->id = Uuid::v4();
        $this->orderId = $orderId;
        $this->partnerId = $partnerId;
        $this->orderIdValue = $orderIdValue;
        $this->eventType = $eventType;
        $this->changes = $changes;
        $this->actorUserId = $actorUserId;
        $this->occurredAt = $occurredAt;
    }
}
