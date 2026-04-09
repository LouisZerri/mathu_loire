<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookTest extends WebTestCase
{
    private const SECRET = 'test_webhook_secret';

    public function testWebhookRejectsWrongSecret(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/wrong_secret', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['eventType' => 'Payment']));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWebhookRejectsEmptyPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/' . self::SECRET, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookRejectsInvalidJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/' . self::SECRET, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not json');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookIgnoresNonPaymentEvents(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/' . self::SECRET, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'eventType' => 'Order',
            'data' => [],
        ]));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testWebhookIgnoresPaymentWithoutRepresentationId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/' . self::SECRET, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '999'],
            'metadata' => [],
        ]));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testWebhookRejectsFakePayment(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso/' . self::SECRET, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '999'],
            'metadata' => ['representation_id' => '999999'],
        ]));

        // Le paiement n'existe pas chez HelloAsso → rejeté
        $this->assertResponseStatusCodeSame(403);
    }
}
