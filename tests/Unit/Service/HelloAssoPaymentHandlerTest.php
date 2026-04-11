<?php

namespace App\Tests\Unit\Service;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Service\HelloAsso\HelloAssoClient;
use App\Service\HelloAsso\HelloAssoPaymentHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HelloAssoPaymentHandlerTest extends TestCase
{
    private HelloAssoPaymentHandler $handler;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->handler = new HelloAssoPaymentHandler(
            baseUrl: 'https://test.local',
            client: $this->createMock(HelloAssoClient::class),
            urlGenerator: $this->createMock(UrlGeneratorInterface::class),
            em: $this->em,
            logger: new NullLogger(),
        );
    }

    public function testHandleNotificationParsesPaymentCorrectly(): void
    {
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
        $this->assertNull($this->handler->handleNotification([
            'eventType' => 'Order',
            'data' => [],
        ]));
    }

    public function testHandleNotificationIgnoresMissingRepresentationId(): void
    {
        $this->assertNull($this->handler->handleNotification([
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '123'],
            'metadata' => [],
        ]));
    }

    public function testHandleNotificationIgnoresEmptyPayload(): void
    {
        $this->assertNull($this->handler->handleNotification([]));
    }

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

        $this->handler->recordPayment($reservation, ['amount' => 900, 'id' => 12345]);
    }

    public function testRecordPaymentWithDecimalAmount(): void
    {
        $reservation = $this->createMock(Reservation::class);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Payment $payment) {
                return $payment->getAmount() === '15.5';
            }));

        $this->handler->recordPayment($reservation, ['amount' => 1550, 'id' => 999]);
    }

    public function testHandleNotificationIdempotent(): void
    {
        $payload = [
            'eventType' => 'Payment',
            'data' => ['amount' => 900, 'id' => '12345'],
            'metadata' => ['representation_id' => '42', 'draft_token' => 'abc'],
        ];

        $this->assertEquals(
            $this->handler->handleNotification($payload),
            $this->handler->handleNotification($payload),
        );
    }
}
