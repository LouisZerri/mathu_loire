<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityTest extends WebTestCase
{
    public function testAdminDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/');

        $this->assertResponseRedirects('/login');
    }

    public function testAdminReservationsRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/reservations/');

        $this->assertResponseRedirects('/login');
    }

    public function testAdminAuditRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/audit/');

        $this->assertResponseRedirects('/login');
    }

    public function testAdminUsersRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/utilisateurs/');

        $this->assertResponseRedirects('/login');
    }

    public function testPublicPagesAccessibleWithoutLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/billetterie/');
        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}
