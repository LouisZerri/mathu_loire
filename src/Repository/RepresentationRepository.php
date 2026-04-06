<?php

namespace App\Repository;

use App\Entity\Representation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Representation>
 */
class RepresentationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Representation::class);
    }

    /**
     * @return Representation[]
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.show', 's')
            ->addSelect('s')
            ->where('r.datetime > :now')
            ->andWhere('r.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'active')
            ->orderBy('r.datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> [representationId => totalBookedPlaces]
     */
    public function findBookedPlacesMap(): array
    {
        $results = $this->getEntityManager()->createQuery(
            'SELECT rep.id, SUM(r.nbAdults + r.nbChildren + r.nbInvitations) as booked
             FROM App\Entity\Reservation r
             JOIN r.representation rep
             WHERE r.status IN (:statuses)
             GROUP BY rep.id'
        )
            ->setParameter('statuses', ['validated', 'pending'])
            ->getResult();

        $map = [];
        foreach ($results as $row) {
            $map[$row['id']] = (int) $row['booked'];
        }

        return $map;
    }
}
