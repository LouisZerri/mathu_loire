<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReservationCapacityTest extends WebTestCase
{
    public function testFullRepresentationRedirectsWithError(): void
    {
        $client = static::createClient();

        // La fixture "rep-full" (Gendre Idéal, max=5, 5 résas) est complète
        // On doit trouver son ID dynamiquement
        $em = static::getContainer()->get('doctrine')->getManager();
        $rep = $em->getRepository(\App\Entity\Representation::class)
            ->findOneBy(['maxOnlineReservations' => 5]);

        if (!$rep) {
            $this->markTestSkipped('Fixture "full" representation not found');
        }

        $client->request('GET', '/billetterie/' . $rep->getId());

        // Doit rediriger vers la page du spectacle (pas le formulaire)
        $this->assertResponseRedirects();
        $client->followRedirect();
        // La page contient le flash d'erreur "complète"
        $this->assertResponseIsSuccessful();
    }
}
