<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Exception\DuplicateOrderException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
final class DoctrineOrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $order): void
    {
        $em = $this->getEntityManager();
        $em->persist($order);

        try {
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateOrderException($order->partnerId, $order->orderId, $e);
        }
    }

    public function findByCompositeKey(string $partnerId, string $orderId): ?Order
    {
        return $this->findOneBy(['partnerId' => $partnerId, 'orderId' => $orderId]);
    }

    public function wrapInTransaction(callable $action): mixed
    {
        return $this->getEntityManager()->wrapInTransaction(static fn (): mixed => $action());
    }
}
