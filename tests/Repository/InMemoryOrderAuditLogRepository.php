<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\OrderAuditLog;
use App\Repository\OrderAuditLogRepositoryInterface;
use RuntimeException;

final class InMemoryOrderAuditLogRepository implements OrderAuditLogRepositoryInterface
{
    /** @var list<OrderAuditLog> */
    public private(set) array $entries = [];
    private bool $failOnSave = false;

    public function save(OrderAuditLog $log): void
    {
        if ($this->failOnSave) {
            throw new RuntimeException('audit log persistence failed (test fake)');
        }
        $this->entries[] = $log;
    }

    public function failNextSave(): void
    {
        $this->failOnSave = true;
    }
}
