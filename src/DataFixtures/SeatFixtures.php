<?php

namespace App\DataFixtures;

use App\Entity\Seat;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SeatFixtures extends Fixture
{
    public const SEAT_REFERENCE_PREFIX = 'seat-';

    /**
     * Plan réel du théâtre de Saint-Mathurin-sur-Loire
     * Rangées A-R, 2 blocs (gauche 1-5, droite 6-11), allée centrale
     * Rangée R numérotation spéciale 4-13, rangée P droite uniquement, rangée A partielle
     */
    public const SEAT_MAP = [
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

    public function load(ObjectManager $manager): void
    {
        foreach (self::SEAT_MAP as $row => $numbers) {
            foreach ($numbers as $number) {
                $seat = new Seat();
                $seat->setRow($row);
                $seat->setNumber($number);
                $seat->setIsActive(true);
                $manager->persist($seat);
                $this->addReference(self::SEAT_REFERENCE_PREFIX . $row . $number, $seat);
            }
        }

        $manager->flush();
    }
}
