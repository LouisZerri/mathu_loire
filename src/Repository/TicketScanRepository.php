<?php

namespace App\Repository;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\TicketScan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketScan>
 */
class TicketScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketScan::class);
    }

    public function findOneByReservationAndIndex(Reservation $reservation, int $ticketIndex): ?TicketScan
    {
        return $this->findOneBy(['reservation' => $reservation, 'ticketIndex' => $ticketIndex]);
    }

    /**
     * Compte les billets scannés par réservation pour une représentation.
     *
     * @return array<int, int> Tableau associatif reservationId => nombre de scans
     */
    public function countScannedByReservationForRepresentation(Representation $representation): array
    {
        $rows = $this->createQueryBuilder('ts')
            ->select('IDENTITY(ts.reservation) AS reservationId, COUNT(ts.id) AS scanCount')
            ->innerJoin('ts.reservation', 'r')
            ->where('r.representation = :rep')
            ->setParameter('rep', $representation)
            ->groupBy('ts.reservation')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['reservationId']] = (int) $row['scanCount'];
        }

        return $map;
    }
}
