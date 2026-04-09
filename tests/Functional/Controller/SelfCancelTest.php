<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Representation;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SelfCancelTest extends WebTestCase
{
    public function testTrackingPageShowsCancelButtonOnlyWhenAllowed(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Trouver une résa validée
        $reservation = $em->getRepository(Reservation::class)->findOneBy(['status' => 'validated']);
        if (!$reservation) {
            $this->markTestSkipped('No validated reservation found');
        }

        $rep = $reservation->getRepresentation();

        // Cas 1 : show dans +5 jours → bouton visible
        $rep->setDatetime(new \DateTime('+5 days'));
        $em->flush();

        $crawler = $client->request('GET', '/billetterie/suivi/' . $reservation->getId() . '/' . $reservation->getToken());
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('button:contains("Annuler ma réservation")')->count(), 'Le bouton d\'annulation devrait être visible à J-5');

        // Cas 2 : show dans +12h → pas de bouton
        $rep->setDatetime(new \DateTime('+12 hours'));
        $em->flush();

        $crawler = $client->request('GET', '/billetterie/suivi/' . $reservation->getId() . '/' . $reservation->getToken());
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $crawler->filter('button:contains("Annuler ma réservation")')->count(), 'Le bouton d\'annulation ne devrait PAS être visible à J-0.5');
    }

    public function testTrackingPageRequiresValidToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $reservation = $em->getRepository(Reservation::class)->findOneBy(['status' => 'validated']);
        if (!$reservation) {
            $this->markTestSkipped('No validated reservation found');
        }

        // Token invalide → 404
        $client->request('GET', '/billetterie/suivi/' . $reservation->getId() . '/fake_token_that_does_not_exist');
        $this->assertResponseStatusCodeSame(404);
    }
}
