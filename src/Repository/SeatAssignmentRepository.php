<?php

namespace App\Repository;

use App\Entity\Representation;
use App\Entity\SeatAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeatAssignment>
 */
class SeatAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeatAssignment::class);
    }

    /**
     * @return SeatAssignment[]
     */
    public function findByRepresentationWithReservation(Representation $representation): array
    {
        return $this->createQueryBuilder('sa')
            ->leftJoin('sa.reservation', 'r')
            ->addSelect('r')
            ->join('sa.seat', 's')
            ->addSelect('s')
            ->where('sa.representation = :rep')
            ->setParameter('rep', $representation)
            ->getQuery()
            ->getResult();
    }
}
