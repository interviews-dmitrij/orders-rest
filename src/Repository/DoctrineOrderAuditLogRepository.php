<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderAuditLog>
 */
final class DoctrineOrderAuditLogRepository extends ServiceEntityRepository implements OrderAuditLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderAuditLog::class);
    }

    public function save(OrderAuditLog $log): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
    }
}
