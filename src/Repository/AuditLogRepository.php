<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findByFilters(?User $user, ?string $action, ?\DateTime $from, ?\DateTime $to, int $page = 1, int $limit = 50): array
    {
        $qb = $this->buildFilterQuery($user, $action, $from, $to)
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(?User $user, ?string $action, ?\DateTime $from, ?\DateTime $to): int
    {
        $qb = $this->buildFilterQuery($user, $action, $from, $to)
            ->select('COUNT(l.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function buildFilterQuery(?User $user, ?string $action, ?\DateTime $from, ?\DateTime $to): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        if ($user) {
            $qb->andWhere('l.user = :user')->setParameter('user', $user);
        }
        if ($action) {
            $qb->andWhere('l.action = :action')->setParameter('action', $action);
        }
        if ($from) {
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('l.createdAt < :to')->setParameter('to', $to);
        }

        return $qb;
    }

    public function purgeOlderThan(\DateTime $before): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function findDistinctActions(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.action')
            ->orderBy('l.action', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'action');
    }
}
