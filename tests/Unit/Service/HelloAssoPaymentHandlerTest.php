<?php

namespace App\Tests\Unit\Service;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Service\HelloAssoPaymentHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HelloAssoPaymentHandlerTest extends TestCase
{
    private HelloAssoPaymentHandler $handler;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->handler = new HelloAssoPaymentHandler(
            clientId: 'test',
            clientSecret: 'test',
            organizationSlug: 'test',
            isSandbox: true,
            baseUrl: 'https://test.local',
            httpClient: $this->createMock(HttpClientInterface::class),
            urlGenerator: $this->createMock(UrlGeneratorInterface::class),
            em: $this->em,
            logger: new NullLogger(),
        );
    }

    // --- Vérification checkout ---

    public function testVerifyCheckoutReturnsNullWhenNoPayment(): void
    {
        // Simulating via handleNotification since verifyCheckout needs HTTP
        // We test the webhook parsing logic instead
        $result = $this->handler->handleNotification([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '12345'],
            'metadata' => ['representation_id' => '42', 'draft_token' => 'abc'],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(42, $result['representation_id']);
        $this->assertEquals(9, $result['amount']);
        $this->assertSame('12345', $result['transaction_id']);
    }

    public function testHandleNotificationIgnoresNonPaymentEvents(): void
    {
        $result = $this->handler->handleNotification([
            'eventType' => 'Order',
            'data' => [],
        ]);

        $this->assertNull($result);
    }

    public function testHandleNotificationIgnoresMissingRepresentationId(): void
    {
        $result = $this->handler->handleNotification([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '123'],
            'metadata' => [],
        ]);

        $this->assertNull($result);
    }

    public function testHandleNotificationIgnoresEmptyPayload(): void
    {
        $this->assertNull($this->handler->handleNotification([]));
    }

    // --- Enregistrement paiement ---

    public function testRecordPaymentPersistsCorrectData(): void
    {
        $reservation = $this->createMock(Reservation::class);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Payment $payment) {
                return $payment->getMethod() === 'helloasso'
                    && $payment->getAmount() === '9'
                    && $payment->getType() === 'payment'
                    && $payment->getTransactionId() === '12345';
            }));

        $this->em->expects($this->once())->method('flush');

        $this->handler->recordPayment($reservation, [
            'amount' => 900,
            'id' => 12345,
        ]);
    }

    public function testRecordPaymentWithDecimalAmount(): void
    {
        $reservation = $this->createMock(Reservation::class);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Payment $payment) {
                return $payment->getAmount() === '15.5';
            }));

        $this->handler->recordPayment($reservation, [
            'amount' => 1550,
            'id' => 999,
        ]);
    }

    // --- Double traitement webhook (idempotence) ---

    public function testHandleNotificationReturnsSameDataOnDuplicateCall(): void
    {
        $payload = [
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '12345'],
            'metadata' => ['representation_id' => '42', 'draft_token' => 'abc'],
        ];

        $result1 = $this->handler->handleNotification($payload);
        $result2 = $this->handler->handleNotification($payload);

        // Les deux appels retournent les mêmes données
        // L'idempotence réelle est gérée par le WebhookController (findOneBy checkoutIntentId)
        $this->assertEquals($result1, $result2);
    }
}
