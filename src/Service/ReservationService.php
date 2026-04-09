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

    public function createFromDraft(array $draft, Representation $representation): Reservation
    {
        $reservation = new Reservation();
        $reservation->setRepresentation($representation);
        $reservation->setStatus('pending');
        $reservation->setNbAdults((int) ($draft['nbAdults'] ?? 0));
        $reservation->setNbChildren((int) ($draft['nbChildren'] ?? 0));
        $reservation->setNbInvitations(0);
        $reservation->setIsPMR((bool) ($draft['isPMR'] ?? false));
        $reservation->setSpectatorLastName($draft['lastName'] ?? '');
        $reservation->setSpectatorFirstName($draft['firstName'] ?? '');
        $reservation->setSpectatorCity($draft['city'] ?? '');
        $reservation->setSpectatorPhone($draft['phone'] ?? '');
        $reservation->setSpectatorEmail($draft['email'] ?? '');
        $reservation->setSpectatorComment($draft['comment'] ?? null);
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
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
