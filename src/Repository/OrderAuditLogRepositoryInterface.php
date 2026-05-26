<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderAuditLog;

interface OrderAuditLogRepositoryInterface
{
    public function save(OrderAuditLog $log): void;
}
