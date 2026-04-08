<?php

namespace App\DataFixtures;

use App\Entity\Representation;
use App\Entity\Show;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RepresentationFixtures extends Fixture implements DependentFixtureInterface
{
    public const REP_REFERENCE_PREFIX = 'rep-';
    public const ALMOST_FULL = 'rep-almost-full';
    public const FULL = 'rep-full';
    public const CANCELLED = 'rep-cancelled';

    public function getDependencies(): array
    {
        return [ShowFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $missPurple = $this->getReference(ShowFixtures::MISS_PURPLE, Show::class);
        $gendreIdeal = $this->getReference(ShowFixtures::GENDRE_IDEAL, Show::class);
        $pauvrePecheur = $this->getReference(ShowFixtures::PAUVRE_PECHEUR, Show::class);
        $chasseEnfer = $this->getReference(ShowFixtures::CHASSE_ENFER, Show::class);

        // === SAISONS PASSÉES (archives) ===
        $pastSeasons = [
            ['show' => $chasseEnfer, 'dates' => ['2022-01-29 20:30', '2022-01-30 15:00', '2022-02-05 20:30', '2022-02-06 15:00']],
            ['show' => $pauvrePecheur, 'dates' => ['2023-01-28 20:30', '2023-01-29 15:00', '2023-02-04 20:30', '2023-02-05 15:00']],
            ['show' => $missPurple, 'dates' => ['2024-01-27 20:30', '2024-01-28 15:00', '2024-02-03 20:30', '2024-02-04 15:00']],
        ];

        $index = 0;
        foreach ($pastSeasons as $season) {
            foreach ($season['dates'] as $date) {
                $rep = $this->createRepresentation($season['show'], $date, 'offline', '8.00', '5.00');
                $manager->persist($rep);
                $this->addReference(self::REP_REFERENCE_PREFIX . $index, $rep);
                $index++;
            }
        }

        // === SAISON 2027 ===
        $upcomingSeasons = [
            ['show' => $missPurple, 'dates' => ['2027-01-30 20:30', '2027-01-31 15:00', '2027-02-06 20:30', '2027-02-07 15:00']],
            ['show' => $gendreIdeal, 'dates' => ['2027-02-13 20:30', '2027-02-14 15:00']],
            ['show' => $pauvrePecheur, 'dates' => ['2027-03-07 20:30', '2027-03-08 15:00', '2027-03-14 20:30', '2027-03-15 15:00']],
            ['show' => $chasseEnfer, 'dates' => ['2027-03-28 20:30', '2027-03-29 15:00', '2027-04-04 20:30', '2027-04-05 15:00']],
        ];

        foreach ($upcomingSeasons as $season) {
            foreach ($season['dates'] as $date) {
                $rep = $this->createRepresentation($season['show'], $date, 'active');
                $manager->persist($rep);
                $this->addReference(self::REP_REFERENCE_PREFIX . $index, $rep);
                $index++;
            }
        }

        // Représentation presque complète (max 10)
        $almostFull = $this->createRepresentation($missPurple, '2027-02-21 20:30', 'active');
        $almostFull->setMaxOnlineReservations(10);
        $manager->persist($almostFull);
        $this->addReference(self::ALMOST_FULL, $almostFull);

        // Représentation complète (max 5)
        $full = $this->createRepresentation($gendreIdeal, '2027-02-22 15:00', 'active');
        $full->setMaxOnlineReservations(5);
        $manager->persist($full);
        $this->addReference(self::FULL, $full);

        // Représentation imminente (J-3) pour tester le badge d'urgence public
        $soonDate = (new \DateTime('+3 days'))->setTime(20, 30)->format('Y-m-d H:i');
        $soon = $this->createRepresentation($pauvrePecheur, $soonDate, 'active');
        $manager->persist($soon);
        $this->addReference('rep-soon', $soon);

        // Représentation annulée
        $cancelled = $this->createRepresentation($gendreIdeal, '2027-02-20 20:30', 'cancelled');
        $manager->persist($cancelled);
        $this->addReference(self::CANCELLED, $cancelled);

        $manager->flush();
    }

    private function createRepresentation(Show $show, string $date, string $status, string $adultPrice = '9.00', string $childPrice = '6.00'): Representation
    {
        $rep = new Representation();
        $rep->setShow($show);
        $rep->setDatetime(new \DateTime($date));
        $rep->setStatus($status);
        $rep->setMaxOnlineReservations(140);
        $rep->setVenueCapacity(175);
        $rep->setAdultPrice($adultPrice);
        $rep->setChildPrice($childPrice);

        return $rep;
    }
}
