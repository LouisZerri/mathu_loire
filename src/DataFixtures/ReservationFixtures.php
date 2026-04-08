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

    private const LAST_NAMES = [
        'Dupuis', 'Bernard', 'Moreau', 'Petit', 'Roux', 'Lefevre', 'Garcia', 'Martin',
        'Durand', 'Robert', 'Richard', 'Simon', 'Laurent', 'Michel', 'Fournier', 'Girard',
        'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefebvre',
        'Faure', 'André', 'Mercier', 'Blanc', 'Guerin', 'Boyer', 'Garnier', 'Chevalier',
        'François', 'Legrand', 'Gauthier', 'Perrin', 'Robin', 'Clement', 'Morin', 'Nicolas',
        'Henry', 'Roussel', 'Mathieu', 'Gautier', 'Masson', 'Marchand', 'Duval', 'Denis',
        'Dumont', 'Marie', 'Lemaire', 'Noel', 'Meyer', 'Dufour', 'Meunier', 'Brun',
        'Blanchard', 'Giraud', 'Joly', 'Rivière', 'Lucas', 'Brunet', 'Gaillard', 'Barbier',
    ];

    private const FIRST_NAMES = [
        'Sophie', 'Pierre', 'Claire', 'Luc', 'Isabelle', 'Marc', 'Nathalie', 'Jean',
        'Marie', 'Paul', 'Julie', 'Thomas', 'Camille', 'Nicolas', 'Emma', 'Antoine',
        'Léa', 'Hugo', 'Chloé', 'Louis', 'Sarah', 'Arthur', 'Manon', 'Jules',
        'Alice', 'Maxime', 'Inès', 'Gabriel', 'Laura', 'Victor', 'Charlotte', 'Raphaël',
        'Élise', 'Baptiste', 'Juliette', 'Alexandre', 'Margaux', 'Clément', 'Anaïs', 'Romain',
    ];

    private const CITIES = [
        'Angers', 'Saumur', 'Saint-Mathurin', 'Brissac', 'Loire-Authion', 'Trélazé',
        'Beaufort-en-Vallée', 'Les Ponts-de-Cé', 'Avrillé', 'Cholet', 'Baugé', 'Doué-la-Fontaine',
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

        // === Représentations 2027 avec taux de remplissage variés ===
        // Cible en places (sur 175 de jauge) pour produire des pourcentages différents
        $targets = [
            12 => 50,   // ~29% — faible
            13 => 95,   // ~54% — moyen
            14 => 130,  // ~74% — bon
            15 => 155,  // ~89% — presque plein (ambre)
            16 => 175,  // 100% — complet (rouge)
            17 => 35,   // ~20% — faible
        ];

        $rep0 = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . 12, Representation::class);

        foreach ($targets as $repIndex => $targetPlaces) {
            $rep = $this->getReference(RepresentationFixtures::REP_REFERENCE_PREFIX . $repIndex, Representation::class);
            $placed = 0;
            $i = 0;

            while ($placed < $targetPlaces) {
                $remaining = $targetPlaces - $placed;
                $groupSize = min($remaining, rand(1, 5));
                $adults = max(1, rand((int) ceil($groupSize / 2), $groupSize));
                $children = $groupSize - $adults;

                $spec = $this->makeSpectator($repIndex, $i);

                $reservation = $this->createReservation($rep, $spec, [
                    'nbAdults' => $adults,
                    'nbChildren' => $children,
                    'isPMR' => ($repIndex === 12 && $i === 3),
                    'createdAt' => new \DateTimeImmutable('-' . rand(1, 30) . ' days'),
                ]);
                $manager->persist($reservation);
                $this->addReference(self::RESERVATION_REFERENCE_PREFIX . $resaIndex, $reservation);
                $resaIndex++;
                $placed += $groupSize;
                $i++;
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
            $res = $this->createReservation($almostFull, $this->makeSpectator(900, $j), [
                'nbAdults' => 1,
                'createdAt' => new \DateTimeImmutable('-' . (10 - $j) . ' days'),
            ]);
            $manager->persist($res);
        }

        for ($j = 0; $j < 5; $j++) {
            $res = $this->createReservation($full, $this->makeSpectator(901, $j), [
                'nbAdults' => 1,
                'createdAt' => new \DateTimeImmutable('-' . (10 - $j) . ' days'),
            ]);
            $manager->persist($res);
        }

        $manager->flush();
    }

    private function makeSpectator(int $repIndex, int $i): array
    {
        // Combinaison déterministe mais variée : assure l'unicité (repIndex,i) sans répétition visible
        $lastIdx = ($repIndex * 37 + $i * 13) % count(self::LAST_NAMES);
        $firstIdx = ($repIndex * 17 + $i * 7) % count(self::FIRST_NAMES);
        $cityIdx = ($repIndex * 5 + $i * 3) % count(self::CITIES);

        $last = self::LAST_NAMES[$lastIdx];
        $first = self::FIRST_NAMES[$firstIdx];
        $city = self::CITIES[$cityIdx];

        $phone = sprintf('06 %02d %02d %02d %02d', ($i * 11) % 100, ($i * 23) % 100, ($repIndex * 7) % 100, ($repIndex + $i) % 100);
        $slug = strtolower(str_replace(['é', 'è', 'ê', 'à', 'ç', ' ', '\''], ['e', 'e', 'e', 'a', 'c', '', ''], $first . '.' . $last));
        $email = $slug . '.r' . $repIndex . 'i' . $i . '@email.com';

        return [$last, $first, $city, $phone, $email];
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
