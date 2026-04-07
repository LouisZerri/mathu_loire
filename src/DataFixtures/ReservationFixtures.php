<?php

namespace App\DataFixtures;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    public const RESERVATION_REFERENCE_PREFIX = 'reservation-';

    private const SPECTATORS = [
        ['Théâtre', 'Les Mathu\'Loire', 'Loire-Authion', '02 41 57 30 81', 'contact@les-mathuloire.com'],
        ['Dupuis', 'Sophie', 'Angers', '06 12 34 56 78', 'sophie.dupuis@email.com'],
        ['Bernard', 'Pierre', 'Saumur', '06 98 76 54 32', 'p.bernard@email.com'],
        ['Moreau', 'Claire', 'Saint-Mathurin', '06 11 22 33 44', 'claire.moreau@email.com'],
        ['Petit', 'Luc', 'Brissac', '06 55 66 77 88', 'luc.petit@email.com'],
        ['Roux', 'Isabelle', 'Loire-Authion', '06 99 88 77 66', 'i.roux@email.com'],
        ['Lefevre', 'Marc', 'Angers', '06 44 33 22 11', 'marc.lefevre@email.com'],
        ['Garcia', 'Nathalie', 'Trélazé', '06 77 88 99 00', 'n.garcia@email.com'],
    ];

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            RepresentationFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
        $resaIndex = 0;

        // === Réservations archivées sur les saisons passées (12 représentations passées : index 0-11) ===
        for ($repIndex = 0; $repIndex < 12; $repIndex++) {
            $rep = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . $repIndex, Representation::class);
            for ($k = 0; $k < 3; $k++) {
                $res = new Reservation();
                $res->setRepresentation($rep);
                $res->setStatus('validated');
                $res->setNbAdults(rand(1, 3));
                $res->setNbChildren(rand(0, 2));
                $res->setNbInvitations(0);
                $res->setIsPMR(false);
                $res->setSpectatorLastName('Archive' . $k);
                $res->setSpectatorFirstName('Spectateur');
                $res->setSpectatorCity('Angers');
                $res->setSpectatorPhone('02 41 00 00 0' . $k);
                $res->setSpectatorEmail('archive' . $k . '@email.com');
                $res->setToken(bin2hex(random_bytes(32)));
                $res->setCreatedAt(new \DateTimeImmutable($rep->getDatetime()->format('Y-m-d') . ' -30 days'));
                $manager->persist($res);
                $this->addReference(self::RESERVATION_REFERENCE_PREFIX . $resaIndex, $res);
                $resaIndex++;
            }
        }

        // === Réservations sur la première représentation 2027 (index 12 = Miss Purple 30 janv 2027) ===
        $rep0 = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . 12, Representation::class);

        foreach (self::SPECTATORS as $i => $spec) {
            $reservation = $this->createReservation($rep0, $spec, [
                'nbAdults' => rand(1, 4),
                'nbChildren' => rand(0, 2),
                'isPMR' => $i === 3,
                'createdAt' => new \DateTimeImmutable('-' . (30 - $i) . ' days'),
            ]);
            $manager->persist($reservation);
            $this->addReference(self::RESERVATION_REFERENCE_PREFIX . $resaIndex, $reservation);
            $resaIndex++;
        }

        // === Réservations sur les autres représentations 2027 (index 13 à 17) ===
        for ($repIndex = 13; $repIndex <= 17; $repIndex++) {
            $rep = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . $repIndex, Representation::class);
            for ($j = 0; $j < 4; $j++) {
                $spec = self::SPECTATORS[array_rand(self::SPECTATORS)];
                $reservation = $this->createReservation($rep, $spec, [
                    'nbAdults' => rand(1, 3),
                    'nbChildren' => rand(0, 2),
                    'createdAt' => new \DateTimeImmutable('-' . rand(1, 20) . ' days'),
                ]);
                $manager->persist($reservation);
                $this->addReference(self::RESERVATION_REFERENCE_PREFIX . $resaIndex, $reservation);
                $resaIndex++;
            }
        }

        // === Réservation annulée ===
        $cancelled = $this->createReservation($rep0, ['Moulin', 'André', 'Angers', '06 00 11 22 33', 'a.moulin@email.com'], [
            'nbAdults' => 2,
            'status' => 'cancelled',
            'createdAt' => new \DateTimeImmutable('-25 days'),
        ]);
        $manager->persist($cancelled);

        // === Réservation avec invitation (créée par admin) ===
        $invit = $this->createReservation($rep0, ['Mairie', 'Élu Local', 'Loire-Authion', '02 41 00 00 00', 'mairie@loire-authion.fr'], [
            'nbInvitations' => 2,
            'createdAt' => new \DateTimeImmutable('-15 days'),
        ]);
        $invit->setCreatedBy($admin);
        $manager->persist($invit);

        // === Réservations pour tester la jauge ===
        $almostFull = $this->getReference(RepresentationFixtures::ALMOST_FULL, Representation::class);
        $full = $this->getReference(RepresentationFixtures::FULL, Representation::class);

        for ($j = 0; $j < 5; $j++) {
            $res = $this->createReservation($almostFull, self::SPECTATORS[$j], [
                'nbAdults' => 1,
                'createdAt' => new \DateTimeImmutable('-' . (10 - $j) . ' days'),
            ]);
            $manager->persist($res);
        }

        for ($j = 0; $j < 5; $j++) {
            $res = $this->createReservation($full, self::SPECTATORS[$j], [
                'nbAdults' => 1,
                'createdAt' => new \DateTimeImmutable('-' . (10 - $j) . ' days'),
            ]);
            $manager->persist($res);
        }

        $manager->flush();
    }

    private function createReservation(Representation $rep, array $spectator, array $options = []): Reservation
    {
        $res = new Reservation();
        $res->setRepresentation($rep);
        $res->setStatus($options['status'] ?? 'validated');
        $res->setNbAdults($options['nbAdults'] ?? 0);
        $res->setNbChildren($options['nbChildren'] ?? 0);
        $res->setNbInvitations($options['nbInvitations'] ?? 0);
        $res->setIsPMR($options['isPMR'] ?? false);
        $res->setSpectatorLastName($spectator[0]);
        $res->setSpectatorFirstName($spectator[1]);
        $res->setSpectatorCity($spectator[2]);
        $res->setSpectatorPhone($spectator[3]);
        $res->setSpectatorEmail($spectator[4]);
        $res->setToken(bin2hex(random_bytes(32)));
        $res->setCreatedAt($options['createdAt'] ?? new \DateTimeImmutable());

        return $res;
    }
}
