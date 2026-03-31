<?php

namespace App\DataFixtures;

use App\Entity\Payment;
use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\Seat;
use App\Entity\SeatAssignment;
use App\Entity\Show;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // === USERS ===
        $admin = new User();
        $admin->setEmail('admin@les-mathuloire.com');
        $admin->setFirstName('Jean');
        $admin->setLastName('Dupont');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $billettiste = new User();
        $billettiste->setEmail('billettiste@les-mathuloire.com');
        $billettiste->setFirstName('Marie');
        $billettiste->setLastName('Martin');
        $billettiste->setRoles(['ROLE_BILLETTISTE']);
        $billettiste->setPassword($this->passwordHasher->hashPassword($billettiste, 'billett123'));
        $manager->persist($billettiste);

        // === SHOWS ===
        $show1 = new Show();
        $show1->setTitle('Miss Purple mène l\'enquête');
        $show1->setDescription('Une comédie policière hilarante où Miss Purple, détective amateur, tente de résoudre un mystère lors d\'un dîner mondain.');
        $manager->persist($show1);

        $show2 = new Show();
        $show2->setTitle('Un gendre idéal');
        $show2->setDescription('Un vaudeville déjanté où un futur gendre tente de faire bonne impression auprès de sa belle-famille.');
        $manager->persist($show2);

        // === REPRESENTATIONS ===
        $representations = [];

        $dates1 = [
            new \DateTime('2027-01-30 20:30'),
            new \DateTime('2027-01-31 15:00'),
            new \DateTime('2027-02-06 20:30'),
            new \DateTime('2027-02-07 15:00'),
        ];

        foreach ($dates1 as $date) {
            $rep = new Representation();
            $rep->setShow($show1);
            $rep->setDatetime($date);
            $rep->setStatus('active');
            $rep->setMaxOnlineReservations(140);
            $rep->setVenueCapacity(175);
            $rep->setAdultPrice('9.00');
            $rep->setChildPrice('6.00');
            $manager->persist($rep);
            $representations[] = $rep;
        }

        $dates2 = [
            new \DateTime('2027-02-13 20:30'),
            new \DateTime('2027-02-14 15:00'),
        ];

        foreach ($dates2 as $date) {
            $rep = new Representation();
            $rep->setShow($show2);
            $rep->setDatetime($date);
            $rep->setStatus('active');
            $rep->setMaxOnlineReservations(140);
            $rep->setVenueCapacity(175);
            $rep->setAdultPrice('9.00');
            $rep->setChildPrice('6.00');
            $manager->persist($rep);
            $representations[] = $rep;
        }

        // Représentation annulée
        $repCancelled = new Representation();
        $repCancelled->setShow($show2);
        $repCancelled->setDatetime(new \DateTime('2027-02-20 20:30'));
        $repCancelled->setStatus('cancelled');
        $repCancelled->setMaxOnlineReservations(140);
        $repCancelled->setVenueCapacity(175);
        $repCancelled->setAdultPrice('9.00');
        $repCancelled->setChildPrice('6.00');
        $manager->persist($repCancelled);

        // === SEATS - Plan réel du théâtre de Saint-Mathurin-sur-Loire ===
        // Rangées A-R, 2 blocs (gauche 1-5, droite 6-11), allée centrale
        // Rangée R numérotation spéciale 4-13, rangée P droite uniquement, rangée A partielle
        $seats = [];
        $seatMap = [
            'A' => [7, 8, 9, 10],
            'B' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'C' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'D' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'E' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'F' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'G' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'H' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'I' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'J' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'K' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'L' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'M' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'N' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'O' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'P' => [6, 7, 8, 9, 10, 11],
            'R' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
        ];

        foreach ($seatMap as $row => $numbers) {
            foreach ($numbers as $number) {
                $seat = new Seat();
                $seat->setRow($row);
                $seat->setNumber($number);
                $seat->setIsActive(true);
                $manager->persist($seat);
                $seats[$row . $number] = $seat;
            }
        }

        // === RESERVATIONS ===
        $spectators = [
            ['Théâtre', 'Les Mathu\'Loire', 'Loire-Authion', '02 41 57 30 81', 'contact@les-mathuloire.com'],
            ['Dupuis', 'Sophie', 'Angers', '06 12 34 56 78', 'sophie.dupuis@email.com'],
            ['Bernard', 'Pierre', 'Saumur', '06 98 76 54 32', 'p.bernard@email.com'],
            ['Moreau', 'Claire', 'Saint-Mathurin', '06 11 22 33 44', 'claire.moreau@email.com'],
            ['Petit', 'Luc', 'Brissac', '06 55 66 77 88', 'luc.petit@email.com'],
            ['Roux', 'Isabelle', 'Loire-Authion', '06 99 88 77 66', 'i.roux@email.com'],
            ['Lefevre', 'Marc', 'Angers', '06 44 33 22 11', 'marc.lefevre@email.com'],
            ['Garcia', 'Nathalie', 'Trélazé', '06 77 88 99 00', 'n.garcia@email.com'],
        ];

        $reservations = [];
        $rep0 = $representations[0];

        foreach ($spectators as $i => $spec) {
            $reservation = new Reservation();
            $reservation->setRepresentation($rep0);
            $reservation->setStatus('validated');
            $reservation->setNbAdults(rand(1, 4));
            $reservation->setNbChildren(rand(0, 2));
            $reservation->setNbInvitations(0);
            $reservation->setIsPMR($i === 3);
            $reservation->setSpectatorLastName($spec[0]);
            $reservation->setSpectatorFirstName($spec[1]);
            $reservation->setSpectatorCity($spec[2]);
            $reservation->setSpectatorPhone($spec[3]);
            $reservation->setSpectatorEmail($spec[4]);
            $reservation->setToken(bin2hex(random_bytes(32)));
            $reservation->setCreatedAt(new \DateTimeImmutable('-' . (30 - $i) . ' days'));
            $manager->persist($reservation);
            $reservations[] = $reservation;
        }

        // Quelques réservations sur d'autres représentations
        foreach ([1, 2, 3, 4, 5] as $repIndex) {
            if (!isset($representations[$repIndex])) {
                continue;
            }
            for ($j = 0; $j < 3; $j++) {
                $spec = $spectators[array_rand($spectators)];
                $reservation = new Reservation();
                $reservation->setRepresentation($representations[$repIndex]);
                $reservation->setStatus('validated');
                $reservation->setNbAdults(rand(1, 3));
                $reservation->setNbChildren(rand(0, 1));
                $reservation->setNbInvitations(0);
                $reservation->setIsPMR(false);
                $reservation->setSpectatorLastName($spec[0]);
                $reservation->setSpectatorFirstName($spec[1]);
                $reservation->setSpectatorCity($spec[2]);
                $reservation->setSpectatorPhone($spec[3]);
                $reservation->setSpectatorEmail($spec[4]);
                $reservation->setToken(bin2hex(random_bytes(32)));
                $reservation->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 20) . ' days'));
                $manager->persist($reservation);
                $reservations[] = $reservation;
            }
        }

        // Réservation annulée
        $resCancelled = new Reservation();
        $resCancelled->setRepresentation($rep0);
        $resCancelled->setStatus('cancelled');
        $resCancelled->setNbAdults(2);
        $resCancelled->setNbChildren(0);
        $resCancelled->setNbInvitations(0);
        $resCancelled->setIsPMR(false);
        $resCancelled->setSpectatorLastName('Moulin');
        $resCancelled->setSpectatorFirstName('André');
        $resCancelled->setSpectatorCity('Angers');
        $resCancelled->setSpectatorPhone('06 00 11 22 33');
        $resCancelled->setSpectatorEmail('a.moulin@email.com');
        $resCancelled->setToken(bin2hex(random_bytes(32)));
        $resCancelled->setCreatedAt(new \DateTimeImmutable('-25 days'));
        $manager->persist($resCancelled);

        // Réservation avec invitation
        $resInvit = new Reservation();
        $resInvit->setRepresentation($rep0);
        $resInvit->setStatus('validated');
        $resInvit->setNbAdults(0);
        $resInvit->setNbChildren(0);
        $resInvit->setNbInvitations(2);
        $resInvit->setIsPMR(false);
        $resInvit->setSpectatorLastName('Mairie');
        $resInvit->setSpectatorFirstName('Élu Local');
        $resInvit->setSpectatorCity('Loire-Authion');
        $resInvit->setSpectatorPhone('02 41 00 00 00');
        $resInvit->setSpectatorEmail('mairie@loire-authion.fr');
        $resInvit->setToken(bin2hex(random_bytes(32)));
        $resInvit->setCreatedAt(new \DateTimeImmutable('-15 days'));
        $resInvit->setCreatedBy($admin);
        $manager->persist($resInvit);

        // === SEAT ASSIGNMENTS (placement sur la première représentation) ===
        $seatIndex = 0;
        $seatKeys = array_keys($seats);
        foreach (array_slice($reservations, 0, 5) as $res) {
            $totalPlaces = $res->getNbAdults() + $res->getNbChildren() + $res->getNbInvitations();
            for ($s = 0; $s < $totalPlaces && $seatIndex < count($seatKeys); $s++) {
                $seat = $seats[$seatKeys[$seatIndex]];
                if ($seat->isActive() && $res->getRepresentation() === $rep0) {
                    $assignment = new SeatAssignment();
                    $assignment->setSeat($seat);
                    $assignment->setReservation($res);
                    $assignment->setRepresentation($rep0);
                    $assignment->setStatus('assigned');
                    $manager->persist($assignment);
                }
                $seatIndex++;
            }
        }

        // Siège bloqué (technique) sur la première représentation
        $blockedAssignment = new SeatAssignment();
        $blockedAssignment->setSeat($seats['E3']);
        $blockedAssignment->setReservation(null);
        $blockedAssignment->setRepresentation($rep0);
        $blockedAssignment->setStatus('blocked');
        $manager->persist($blockedAssignment);

        // === PAYMENTS (HelloAsso) ===
        foreach (array_slice($reservations, 0, 4) as $res) {
            $total = ($res->getNbAdults() * 9) + ($res->getNbChildren() * 6);
            if ($total > 0) {
                $payment = new Payment();
                $payment->setReservation($res);
                $payment->setMethod('helloasso');
                $payment->setAmount((string) $total);
                $payment->setType('payment');
                $payment->setTransactionId('ha_' . bin2hex(random_bytes(8)));
                $payment->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 15) . ' days'));
                $manager->persist($payment);
            }
        }

        $manager->flush();
    }
}
