<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\OrderAuditLog;
use App\Repository\OrderAuditLogRepositoryInterface;

final class InMemoryOrderAuditLogRepository implements OrderAuditLogRepositoryInterface
{
    /** @var list<OrderAuditLog> */
    public private(set) array $entries = [];

    public function save(OrderAuditLog $log): void
    {
        $this->entries[] = $log;
    }
}
