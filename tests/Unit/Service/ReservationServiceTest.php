<?php

namespace App\Tests\Unit\Service;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Entity\SeatAssignment;
use App\Entity\Show;
use App\Service\Reservation\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new ReservationService($this->em);
    }

    // --- Calcul du prix ---

    public function testComputeTotalAdultsOnly(): void
    {
        $reservation = $this->makeReservation(3, 0, adultPrice: '9.00', childPrice: '6.00');

        $this->assertSame(27.0, $this->service->computeTotal($reservation));
    }

    public function testComputeTotalChildrenOnly(): void
    {
        $reservation = $this->makeReservation(0, 2, adultPrice: '9.00', childPrice: '6.00');

        $this->assertSame(12.0, $this->service->computeTotal($reservation));
    }

    public function testComputeTotalMixed(): void
    {
        $reservation = $this->makeReservation(2, 3, adultPrice: '9.00', childPrice: '6.00');

        $this->assertSame(36.0, $this->service->computeTotal($reservation));
    }

    public function testComputeTotalZeroPlaces(): void
    {
        $reservation = $this->makeReservation(0, 0, adultPrice: '9.00', childPrice: '6.00');

        $this->assertSame(0.0, $this->service->computeTotal($reservation));
    }

    // --- Annulation ---

    public function testCancelSetsStatusToCancelled(): void
    {
        $reservation = $this->makeReservation(2, 0);
        $reservation->setStatus('validated');

        $this->em->expects($this->once())->method('flush');

        $this->service->cancel($reservation);

        $this->assertSame('cancelled', $reservation->getStatus());
        $this->assertNotNull($reservation->getUpdatedAt());
    }

    public function testCancelReleasesAssignedSeatsOnly(): void
    {
        $reservation = $this->makeReservation(2, 0);
        $reservation->setStatus('validated');

        $seat1 = new \App\Entity\Seat();
        $seat1->setRow('A');
        $seat1->setNumber(1);
        $seat1->setIsActive(true);

        $seat2 = new \App\Entity\Seat();
        $seat2->setRow('A');
        $seat2->setNumber(2);
        $seat2->setIsActive(true);

        $assigned = new SeatAssignment();
        $assigned->setSeat($seat1);
        $assigned->setRepresentation($reservation->getRepresentation());
        $assigned->setReservation($reservation);
        $assigned->setStatus('assigned');

        $blocked = new SeatAssignment();
        $blocked->setSeat($seat2);
        $blocked->setRepresentation($reservation->getRepresentation());
        $blocked->setReservation(null);
        $blocked->setStatus('blocked');

        $reservation->addSeatAssignment($assigned);
        // blocked n'est pas lié à la réservation, donc pas dans la collection

        $removedItems = [];
        $this->em->expects($this->atLeastOnce())
            ->method('remove')
            ->willReturnCallback(function ($item) use (&$removedItems) {
                $removedItems[] = $item;
            });

        $this->service->cancel($reservation);

        // Seul le siège "assigned" doit être supprimé
        $this->assertCount(1, $removedItems);
        $this->assertSame($assigned, $removedItems[0]);
        $this->assertSame('cancelled', $reservation->getStatus());
    }

    public function testCancelWithNoSeatsDoesNotCallRemove(): void
    {
        $reservation = $this->makeReservation(1, 0);
        $reservation->setStatus('validated');

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->once())->method('flush');

        $this->service->cancel($reservation);

        $this->assertSame('cancelled', $reservation->getStatus());
    }

    // --- Création depuis draft ---

    public function testCreateFromDraftProducesValidReservation(): void
    {
        $show = new Show();
        $show->setTitle('Test Show');
        $representation = new Representation();
        $representation->setShow($show);
        $representation->setDatetime(new \DateTime('+7 days'));
        $representation->setAdultPrice('9.00');
        $representation->setChildPrice('6.00');

        $draft = [
            'nbAdults' => 2,
            'nbChildren' => 1,
            'isPMR' => true,
            'lastName' => 'Dupont',
            'firstName' => 'Marie',
            'city' => 'Angers',
            'phone' => '06 12 34 56 78',
            'email' => 'marie@test.com',
            'comment' => null,
        ];

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $reservation = $this->service->createFromDraft($draft, $representation);

        $this->assertSame('pending', $reservation->getStatus());
        $this->assertSame(2, $reservation->getNbAdults());
        $this->assertSame(1, $reservation->getNbChildren());
        $this->assertSame(0, $reservation->getNbInvitations());
        $this->assertTrue($reservation->isPMR());
        $this->assertSame('Dupont', $reservation->getSpectatorLastName());
        $this->assertSame('marie@test.com', $reservation->getSpectatorEmail());
        $this->assertSame(64, strlen($reservation->getToken()));
        $this->assertNotNull($reservation->getCreatedAt());
        $this->assertSame($representation, $reservation->getRepresentation());
    }

    // --- Helpers ---

    private function makeReservation(int $adults, int $children, string $adultPrice = '9.00', string $childPrice = '6.00'): Reservation
    {
        $show = new Show();
        $show->setTitle('Test');

        $rep = new Representation();
        $rep->setShow($show);
        $rep->setDatetime(new \DateTime());
        $rep->setAdultPrice($adultPrice);
        $rep->setChildPrice($childPrice);

        $reservation = new Reservation();
        $reservation->setRepresentation($rep);
        $reservation->setNbAdults($adults);
        $reservation->setNbChildren($children);
        $reservation->setNbInvitations(0);
        $reservation->setIsPMR(false);
        $reservation->setSpectatorLastName('Test');
        $reservation->setSpectatorFirstName('User');
        $reservation->setSpectatorCity('Angers');
        $reservation->setSpectatorPhone('06 00 00 00 00');
        $reservation->setSpectatorEmail('test@test.com');
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        return $reservation;
    }
}
