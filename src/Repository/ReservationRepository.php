<?php

namespace App\Repository;

use App\Entity\Representation;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findSeasonStats(?int $year = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'SUM(r.nbAdults) as totalAdults',
                'SUM(r.nbChildren) as totalChildren',
                'SUM(r.nbInvitations) as totalInvitations',
                'COUNT(r.id) as totalReservations',
                'SUM(r.nbAdults * rep.adultPrice + r.nbChildren * rep.childPrice) as totalRevenue'
            )
            ->join('r.representation', 'rep')
            ->where('r.status = :status')
            ->setParameter('status', 'validated');

        if ($year) {
            $qb->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        return $qb->getQuery()->getSingleResult();
    }

    public function countNewSince(?\DateTimeImmutable $since): int
    {
        if (!$since) {
            return 0;
        }

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.createdAt > :since')
            ->andWhere('r.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', 'validated')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Reservation[]
     */
    public function findForReminder(\DateTime $targetDate): array
    {
        $start = (clone $targetDate)->setTime(0, 0, 0);
        $end = (clone $targetDate)->setTime(23, 59, 59);

        return $this->createQueryBuilder('r')
            ->join('r.representation', 'rep')
            ->join('rep.show', 's')
            ->addSelect('rep', 's')
            ->where('rep.datetime >= :start')
            ->andWhere('rep.datetime <= :end')
            ->andWhere('r.status = :status')
            ->andWhere('rep.status = :repStatus')
            ->andWhere('r.reminderSentAt IS NULL')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'validated')
            ->setParameter('repStatus', 'active')
            ->orderBy('rep.datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{showTitle: string, revenue: float}[]
     */
    public function findRevenueByShow(?int $year = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                's.title as showTitle',
                'SUM(r.nbAdults * rep.adultPrice + r.nbChildren * rep.childPrice) as revenue',
            )
            ->join('r.representation', 'rep')
            ->join('rep.show', 's')
            ->where('r.status = :status')
            ->setParameter('status', 'validated')
            ->groupBy('s.id, s.title')
            ->orderBy('revenue', 'DESC');

        if ($year) {
            $qb->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{city: string, count: int, totalPlaces: int}[]
     */
    public function findCityStats(?int $year = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'r.spectatorCity as city',
                'COUNT(r.id) as count',
                'SUM(r.nbAdults + r.nbChildren + r.nbInvitations + r.nbGroups) as totalPlaces',
            )
            ->where('r.status = :status')
            ->setParameter('status', 'validated')
            ->groupBy('r.spectatorCity')
            ->orderBy('totalPlaces', 'DESC');

        if ($year) {
            $qb->join('r.representation', 'rep')
               ->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return \App\Entity\Reservation[]
     */
    public function findByRepresentationWithAssignments(Representation $representation): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.seatAssignments', 'sa')
            ->addSelect('sa')
            ->where('r.representation = :rep')
            ->andWhere('r.status = :status')
            ->setParameter('rep', $representation)
            ->setParameter('status', 'validated')
            ->orderBy('r.spectatorLastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(?Representation $representation = null, ?string $status = null, int $page = 1, int $limit = 20, ?int $year = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.representation', 'rep')
            ->join('rep.show', 's')
            ->leftJoin('r.payments', 'p')
            ->addSelect('rep', 's', 'p')
            ->orderBy('r.createdAt', 'DESC');

        if ($representation) {
            $qb->andWhere('r.representation = :rep')
               ->setParameter('rep', $representation);
        }

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        if ($year) {
            $qb->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        if ($search) {
            $qb->andWhere('r.spectatorLastName LIKE :search OR r.spectatorFirstName LIKE :search OR r.spectatorEmail LIKE :search OR r.spectatorPhone LIKE :search OR r.id = :searchId')
               ->setParameter('search', '%' . $search . '%')
               ->setParameter('searchId', (int) $search);
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(?Representation $representation = null, ?string $status = null, ?int $year = null, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.representation', 'rep');

        if ($representation) {
            $qb->andWhere('r.representation = :rep')
               ->setParameter('rep', $representation);
        }

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        if ($year) {
            $qb->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        if ($search) {
            $qb->andWhere('r.spectatorLastName LIKE :search OR r.spectatorFirstName LIKE :search OR r.spectatorEmail LIKE :search OR r.spectatorPhone LIKE :search OR r.id = :searchId')
               ->setParameter('search', '%' . $search . '%')
               ->setParameter('searchId', (int) $search);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findRepresentationStats(?int $year = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'rep.id as repId',
                's.title as showTitle',
                'rep.datetime as datetime',
                'rep.status as repStatus',
                'rep.venueCapacity as venueCapacity',
                'rep.maxOnlineReservations as maxOnline',
                'rep.adultPrice as adultPrice',
                'rep.childPrice as childPrice',
                'SUM(r.nbAdults) as totalAdults',
                'SUM(r.nbChildren) as totalChildren',
                'SUM(r.nbInvitations) as totalInvitations',
                'COUNT(r.id) as totalReservations',
                'SUM(r.nbAdults * rep.adultPrice + r.nbChildren * rep.childPrice) as revenue'
            )
            ->join('r.representation', 'rep')
            ->join('rep.show', 's')
            ->where('r.status = :status')
            ->setParameter('status', 'validated')
            ->groupBy('rep.id, s.title, rep.datetime, rep.status, rep.venueCapacity, rep.maxOnlineReservations, rep.adultPrice, rep.childPrice')
            ->orderBy('rep.datetime', 'ASC');

        if ($year) {
            $qb->andWhere('rep.datetime >= :start AND rep.datetime < :end')
               ->setParameter('start', new \DateTime($year . '-01-01'))
               ->setParameter('end', new \DateTime(($year + 1) . '-01-01'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findTodayReservations(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->createQueryBuilder('r')
            ->join('r.representation', 'rep')
            ->join('rep.show', 's')
            ->addSelect('rep', 's')
            ->where('r.createdAt >= :today')
            ->andWhere('r.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
