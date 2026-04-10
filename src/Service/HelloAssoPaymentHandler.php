<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Representation;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HelloAssoPaymentHandler
{
    private ?string $accessToken = null;

    public function __construct(
        #[Autowire('%env(HELLOASSO_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(HELLOASSO_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(HELLOASSO_ORGANIZATION_SLUG)%')]
        private string $organizationSlug,
        #[Autowire('%env(bool:HELLOASSO_IS_SANDBOX)%')]
        private bool $isSandbox,
        #[Autowire('%env(APP_BASE_URL)%')]
        private string $baseUrl,
        private HttpClientInterface $httpClient,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function createCheckoutIntent(Reservation $reservation, float $total): array
    {
        $token = $this->authenticate();
        $representation = $reservation->getRepresentation();

        $backUrl = $this->baseUrl . $this->urlGenerator->generate(
            'app_reservation_cancel',
            ['id' => $reservation->getId(), 'token' => $reservation->getToken()]
        );
        $returnUrl = $this->baseUrl . $this->urlGenerator->generate(
            'app_reservation_return',
            ['id' => $reservation->getId(), 'token' => $reservation->getToken()]
        );

        $this->logger->info('HelloAsso URLs - back: {back} return: {return}', ['back' => $backUrl, 'return' => $returnUrl]);

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/organizations/' . $this->organizationSlug . '/checkout-intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'totalAmount' => (int) ($total * 100),
                'initialAmount' => (int) ($total * 100),
                'itemName' => $representation->getShow()->getTitle() . ' - ' . $representation->getDatetime()->format('d/m/Y H:i'),
                'backUrl' => $backUrl,
                'errorUrl' => $backUrl,
                'returnUrl' => $returnUrl,
                'containsDonation' => false,
                'metadata' => [
                    'reservation_id' => (string) $reservation->getId(),
                ],
                'payer' => [
                    'firstName' => $reservation->getSpectatorFirstName(),
                    'lastName' => $reservation->getSpectatorLastName(),
                    'email' => $reservation->getSpectatorEmail(),
                ],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            $errorBody = $response->getContent(false);
            $this->logger->error('HelloAsso checkout error: {body}', ['body' => $errorBody]);
            throw new \RuntimeException('HelloAsso checkout failed: ' . $errorBody);
        }

        $data = $response->toArray(false);

        $this->logger->info('HelloAsso checkout intent créé : {id}', ['id' => $data['id']]);

        return $data;
    }

    public function createCheckoutIntentFromDraft(array $draft, Representation $representation, float $total, string $draftToken): array
    {
        $token = $this->authenticate();

        $backUrl = $this->baseUrl . $this->urlGenerator->generate(
            'app_reservation_cancel',
            ['id' => $representation->getId()]
        );
        $returnUrl = $this->baseUrl . $this->urlGenerator->generate(
            'app_reservation_return',
            ['id' => $representation->getId()]
        );

        $this->logger->info('HelloAsso URLs - back: {back} return: {return}', ['back' => $backUrl, 'return' => $returnUrl]);

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/organizations/' . $this->organizationSlug . '/checkout-intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'totalAmount' => (int) ($total * 100),
                'initialAmount' => (int) ($total * 100),
                'itemName' => $representation->getShow()->getTitle() . ' - ' . $representation->getDatetime()->format('d/m/Y H:i'),
                'backUrl' => $backUrl,
                'errorUrl' => $backUrl,
                'returnUrl' => $returnUrl,
                'containsDonation' => false,
                'metadata' => [
                    'draft_token' => $draftToken,
                    'representation_id' => (string) $representation->getId(),
                ],
                'payer' => [
                    'firstName' => $draft['firstName'] ?? '',
                    'lastName' => $draft['lastName'] ?? '',
                    'email' => $draft['email'] ?? '',
                ],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            $errorBody = $response->getContent(false);
            $this->logger->error('HelloAsso checkout error: {body}', ['body' => $errorBody]);
            throw new \RuntimeException('HelloAsso checkout failed: ' . $errorBody);
        }

        $data = $response->toArray(false);
        $this->logger->info('HelloAsso checkout intent créé : {id}', ['id' => $data['id']]);

        return $data;
    }

    public function verifyCheckout(int $checkoutIntentId): ?array
    {
        $data = $this->getCheckoutIntent($checkoutIntentId);

        if (!isset($data['order']['payments'][0])) {
            return null;
        }

        $paymentData = $data['order']['payments'][0];

        if ($paymentData['state'] !== 'Authorized') {
            return null;
        }

        return $paymentData;
    }

    public function recordPayment(Reservation $reservation, array $paymentData): void
    {
        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setMethod('helloasso');
        $payment->setAmount((string) ($paymentData['amount'] / 100));
        $payment->setType('payment');
        $payment->setTransactionId((string) $paymentData['id']);
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();
    }

    public function verifyPaymentExists(string $paymentId): bool
    {
        try {
            $token = $this->authenticate();

            $response = $this->httpClient->request('GET', $this->getApiUrl() . '/payments/' . $paymentId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $data = $response->toArray(false);

            return ($data['state'] ?? '') === 'Authorized';
        } catch (\Throwable $e) {
            $this->logger->error('HelloAsso payment verification failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function getCheckoutIntent(int $checkoutIntentId): array
    {
        $token = $this->authenticate();

        $response = $this->httpClient->request('GET', $this->getApiUrl() . '/organizations/' . $this->organizationSlug . '/checkout-intents/' . $checkoutIntentId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $response->toArray();
    }

    public function handleReturn(Reservation $reservation, int $checkoutIntentId): bool
    {
        $data = $this->getCheckoutIntent($checkoutIntentId);

        if (!isset($data['order']['payments'][0])) {
            return false;
        }

        $paymentData = $data['order']['payments'][0];

        if ($paymentData['state'] !== 'Authorized') {
            return false;
        }

        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setMethod('helloasso');
        $payment->setAmount((string) ($paymentData['amount'] / 100));
        $payment->setType('payment');
        $payment->setTransactionId((string) $paymentData['id']);
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();

        return true;
    }

    public function refund(Reservation $reservation): bool
    {
        $token = $this->authenticate();

        $payments = $reservation->getPayments();

        foreach ($payments as $payment) {
            if ($payment->getMethod() !== 'helloasso' || $payment->getType() !== 'payment' || !$payment->getTransactionId()) {
                continue;
            }

            $response = $this->httpClient->request('POST', $this->getApiUrl() . '/payments/' . $payment->getTransactionId() . '/refund', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $refundPayment = new Payment();
                $refundPayment->setReservation($reservation);
                $refundPayment->setMethod('helloasso');
                $refundPayment->setAmount('-' . $payment->getAmount());
                $refundPayment->setType('refund');
                $refundPayment->setTransactionId($payment->getTransactionId() . '_refund');
                $refundPayment->setCreatedAt(new \DateTimeImmutable());

                $this->em->persist($refundPayment);

                return true;
            }

            $this->logger->error('HelloAsso refund failed: {body}', [
                'body' => $response->getContent(false),
            ]);
        }

        return false;
    }

    public function handleNotification(array $data): ?array
    {
        if (($data['eventType'] ?? '') !== 'Payment') {
            return null;
        }

        $paymentInfo = $data['data'] ?? [];
        $metadata = $data['metadata'] ?? [];
        $representationId = $metadata['representation_id'] ?? null;

        if (!$representationId) {
            return null;
        }

        $amount = ($paymentInfo['amount'] ?? 0) / 100;

        return [
            'representation_id' => (int) $representationId,
            'draft_token' => $metadata['draft_token'] ?? null,
            'amount' => $amount,
            'transaction_id' => (string) ($paymentInfo['id'] ?? ''),
            'payer' => $paymentInfo['payer'] ?? [],
        ];
    }

    private function authenticate(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = $this->httpClient->request('POST', $this->getAuthUrl() . '/token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = $response->toArray();
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    private function getApiUrl(): string
    {
        return $this->isSandbox
            ? 'https://api.helloasso-sandbox.com/v5'
            : 'https://api.helloasso.com/v5';
    }

    private function getAuthUrl(): string
    {
        return $this->isSandbox
            ? 'https://api.helloasso-sandbox.com/oauth2'
            : 'https://api.helloasso.com/oauth2';
    }
}
