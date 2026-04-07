<?php

namespace App\DataFixtures;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\Seat;
use App\Entity\SeatAssignment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SeatAssignmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            SeatFixtures::class,
            ReservationFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $rep0 = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . 12, Representation::class);
        $rep1 = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . 13, Representation::class);

        // Récupérer tous les sièges via leurs références
        $seats = [];
        foreach (SeatFixtures::SEAT_MAP as $row => $numbers) {
            foreach ($numbers as $number) {
                $seats[] = $this->getReference(SeatFixtures::SEAT_REFERENCE_PREFIX . $row . $number, Seat::class);
            }
        }

        // Placement automatique sur les 2 premières représentations 2027
        foreach ([$rep0, $rep1] as $rep) {
            $seatIndex = 0;
            $reservations = $manager->getRepository(Reservation::class)->findBy(['representation' => $rep, 'status' => 'validated']);

            foreach ($reservations as $res) {
                $totalPlaces = $res->getNbAdults() + $res->getNbChildren() + $res->getNbInvitations();
                for ($s = 0; $s < $totalPlaces && $seatIndex < count($seats); $s++) {
                    $seat = $seats[$seatIndex];
                    if ($seat->isActive()) {
                        $assignment = new SeatAssignment();
                        $assignment->setSeat($seat);
                        $assignment->setReservation($res);
                        $assignment->setRepresentation($rep);
                        $assignment->setStatus('assigned');
                        $manager->persist($assignment);
                    }
                    $seatIndex++;
                }
            }
        }

        // Siège bloqué (technique) sur la première représentation
        $blockedSeat = $this->getReference(SeatFixtures::SEAT_REFERENCE_PREFIX . 'E3', Seat::class);
        $blocked = new SeatAssignment();
        $blocked->setSeat($blockedSeat);
        $blocked->setReservation(null);
        $blocked->setRepresentation($rep0);
        $blocked->setStatus('blocked');
        $manager->persist($blocked);

        $manager->flush();
    }
}
