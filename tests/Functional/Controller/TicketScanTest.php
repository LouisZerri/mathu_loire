<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Reservation;
use App\Entity\TicketScan;
use App\Entity\User;
use App\Service\Pdf\TicketQrCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TicketScanTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private TicketQrCodeGenerator $qrGenerator;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->qrGenerator = static::getContainer()->get(TicketQrCodeGenerator::class);

        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'l.zerri@gmail.com']);
        $this->client->loginUser($admin);
    }

    public function testInvalidHmacReturnsInvalidScreen(): void
    {
        $reservation = $this->getValidatedReservation();

        $this->client->request('GET', '/admin/scan/' . $reservation->getId() . '-1/deadbeefdeadbeef');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Code invalide');
    }

    public function testFirstScanCreatesTicketScanInDatabase(): void
    {
        $reservation = $this->getValidatedReservation();
        $this->em->createQuery('DELETE FROM App\Entity\TicketScan ts WHERE ts.reservation = :r')
            ->setParameter('r', $reservation)
            ->execute();

        $url = $this->qrGenerator->buildScanUrl($reservation, 1);
        $this->client->request('GET', parse_url($url, PHP_URL_PATH));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Entrée autorisée');

        $scan = $this->em->getRepository(TicketScan::class)
            ->findOneBy(['reservation' => $reservation, 'ticketIndex' => 1]);
        $this->assertNotNull($scan, 'Un TicketScan doit être créé en base.');
    }

    public function testDoubleScanReturnsAlreadyScreen(): void
    {
        $reservation = $this->getValidatedReservation();
        $this->em->createQuery('DELETE FROM App\Entity\TicketScan ts WHERE ts.reservation = :r')
            ->setParameter('r', $reservation)
            ->execute();

        $path = parse_url($this->qrGenerator->buildScanUrl($reservation, 1), PHP_URL_PATH);

        $this->client->request('GET', $path);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Déjà contrôlé');
    }

    public function testScanCancelledReservationReturnsCancelledScreen(): void
    {
        $reservation = $this->getValidatedReservation();
        $originalStatus = $reservation->getStatus();
        $reservation->setStatus('cancelled');
        $this->em->flush();

        $path = parse_url($this->qrGenerator->buildScanUrl($reservation, 1), PHP_URL_PATH);
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Réservation annulée');

        $reservation->setStatus($originalStatus);
        $this->em->flush();
    }

    private function getValidatedReservation(): Reservation
    {
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['status' => 'validated']);
        if (!$reservation) {
            $this->markTestSkipped('Aucune réservation validée dans les fixtures.');
        }

        return $reservation;
    }
}
