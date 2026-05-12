<?php

namespace App\Tests\Unit\Service;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\Show;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que les contraintes spectatorCity/Phone/Email sont déclenchées par le groupe
 * "public" mais pas par le groupe "Default" — c'est ce qui permet au formulaire admin
 * de soumettre une réservation sans ces champs.
 */
class ReservationValidationGroupsTest extends KernelTestCase
{
    private function makeReservation(): Reservation
    {
        $show = new Show();
        $show->setTitle('Test');
        $show->setSynopsis('Lorem ipsum dolor sit amet consectetur adipiscing elit.');

        $representation = new Representation();
        $representation->setShow($show);
        $representation->setDatetime(new \DateTime('+1 week'));
        $representation->setStatus('active');
        $representation->setMaxOnlineReservations(140);
        $representation->setVenueCapacity(175);
        $representation->setAdultPrice('9.00');
        $representation->setChildPrice('6.00');

        $reservation = new Reservation();
        $reservation->setRepresentation($representation);
        $reservation->setStatus('validated');
        $reservation->setNbAdults(1);
        $reservation->setNbChildren(0);
        $reservation->setSpectatorLastName('Dupont');
        $reservation->setSpectatorFirstName('Jean');
        $reservation->setSpectatorCity('');
        $reservation->setSpectatorPhone('');
        $reservation->setSpectatorEmail('');

        return $reservation;
    }

    public function testAdminContextAcceptsEmptyEmailPhoneCity(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get('validator');

        $errors = $validator->validate($this->makeReservation(), null, ['Default']);

        $messages = array_map(fn ($e) => $e->getPropertyPath() . ': ' . $e->getMessage(), iterator_to_array($errors));
        $this->assertCount(0, $errors, "Erreurs inattendues en groupe Default: " . implode(', ', $messages));
    }

    public function testPublicContextRejectsEmptyEmailPhoneCity(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get('validator');

        $errors = $validator->validate($this->makeReservation(), null, ['Default', 'public']);

        $properties = array_map(fn ($e) => $e->getPropertyPath(), iterator_to_array($errors));
        $this->assertContains('spectatorCity', $properties);
        $this->assertContains('spectatorPhone', $properties);
        $this->assertContains('spectatorEmail', $properties);
    }
}
