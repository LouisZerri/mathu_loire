<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookTest extends WebTestCase
{
    public function testWebhookRejectsEmptyPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookRejectsInvalidJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not json');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookIgnoresNonPaymentEvents(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso', [], [], [
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
        $client->request('POST', '/webhook/helloasso', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '999'],
            'metadata' => [],
        ]));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testWebhookIgnoresNonexistentRepresentation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/webhook/helloasso', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '999'],
            'metadata' => ['representation_id' => '999999'],
        ]));

        $this->assertResponseStatusCodeSame(200);
    }
}
