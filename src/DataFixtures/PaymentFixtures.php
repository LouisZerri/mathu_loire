<?php

namespace App\DataFixtures;

use App\Entity\Payment;
use App\Entity\Reservation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [ReservationFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Crée un paiement HelloAsso pour les 4 premières réservations validées 2027
        for ($i = 36; $i < 40; $i++) {
            try {
                $reservation = $this->getReference(ReservationFixtures::RESERVATION_REFERENCE_PREFIX . $i, Reservation::class);
            } catch (\Exception $e) {
                continue;
            }

            $rep = $reservation->getRepresentation();
            $total = ($reservation->getNbAdults() * (float) $rep->getAdultPrice())
                   + ($reservation->getNbChildren() * (float) $rep->getChildPrice());

            if ($total <= 0) {
                continue;
            }

            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setMethod('helloasso');
            $payment->setAmount((string) $total);
            $payment->setType('payment');
            $payment->setTransactionId('ha_' . bin2hex(random_bytes(8)));
            $payment->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 15) . ' days'));
            $manager->persist($payment);
        }

        $manager->flush();
    }
}
