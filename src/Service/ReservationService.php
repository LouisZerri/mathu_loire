<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Representation;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function create(Reservation $reservation, Representation $representation): void
    {
        $reservation->setRepresentation($representation);
        $reservation->setStatus('pending');
        $reservation->setNbInvitations(0);
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();
    }

    public function save(): void
    {
        $this->em->flush();
    }

    public function confirm(Reservation $reservation): void
    {
        $reservation->setStatus('validated');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function cancel(Reservation $reservation): void
    {
        $reservation->setStatus('cancelled');
        $reservation->setUpdatedAt(new \DateTimeImmutable());

        // Libérer tous les sièges assignés à cette réservation
        foreach ($reservation->getSeatAssignments() as $assignment) {
            if ($assignment->getStatus() === 'assigned') {
                $this->em->remove($assignment);
            }
        }

        $this->em->flush();
    }

    public function computeTotal(Reservation $reservation): float
    {
        $representation = $reservation->getRepresentation();

        return ($reservation->getNbAdults() * (float) $representation->getAdultPrice())
             + ($reservation->getNbChildren() * (float) $representation->getChildPrice());
    }
}
