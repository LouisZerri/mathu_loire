<?php

namespace App\Service\HelloAsso;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP bas niveau pour l'API HelloAsso (checkout, paiements, remboursements).
 */
class HelloAssoClient
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
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Crée une intention de paiement sur HelloAsso et retourne la réponse API.
     *
     * @param array $payload Données du checkout (totalAmount, itemName, backUrl, returnUrl, payer, etc.)
     * @return array Réponse brute de l'API HelloAsso
     */
    public function createCheckoutIntent(array $payload): array
    {
        $token = $this->authenticate();

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/organizations/' . $this->organizationSlug . '/checkout-intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
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

    /**
     * Récupère le statut d'une intention de checkout par son identifiant.
     *
     * @param int $checkoutIntentId Identifiant de l'intention de checkout
     * @return array Réponse brute de l'API HelloAsso
     */
    public function getCheckoutIntent(int $checkoutIntentId): array
    {
        $token = $this->authenticate();

        $response = $this->httpClient->request('GET', $this->getApiUrl() . '/organizations/' . $this->organizationSlug . '/checkout-intents/' . $checkoutIntentId, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        return $response->toArray();
    }

    /**
     * Récupère les détails d'un paiement, ou null si introuvable.
     *
     * @param string $paymentId Identifiant du paiement HelloAsso
     * @return array|null Données du paiement ou null en cas d'erreur
     */
    public function getPayment(string $paymentId): ?array
    {
        try {
            $token = $this->authenticate();

            $response = $this->httpClient->request('GET', $this->getApiUrl() . '/payments/' . $paymentId, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('HelloAsso payment fetch failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Demande le remboursement d'une transaction sur HelloAsso.
     *
     * @param string $transactionId Identifiant de la transaction à rembourser
     * @return bool Vrai si le remboursement a réussi
     */
    public function refundPayment(string $transactionId): bool
    {
        $token = $this->authenticate();

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/payments/' . $transactionId . '/refund', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        if ($response->getStatusCode() === 200) {
            return true;
        }

        $this->logger->error('HelloAsso refund failed: {body}', ['body' => $response->getContent(false)]);

        return false;
    }

    /**
     * OAuth2 client_credentials. Le token est caché en mémoire pour la durée de la requête HTTP.
     * HelloAsso ne fournit pas de refresh_token, un nouveau token est demandé à chaque requête PHP.
     *
     * @return string Token d'accès OAuth2
     */
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

    /**
     * Retourne l'URL de base de l'API HelloAsso selon l'environnement (sandbox ou production).
     *
     * @return string URL de base de l'API
     */
    private function getApiUrl(): string
    {
        return $this->isSandbox
            ? 'https://api.helloasso-sandbox.com/v5'
            : 'https://api.helloasso.com/v5';
    }

    /**
     * Retourne l'URL d'authentification OAuth2 HelloAsso selon l'environnement.
     *
     * @return string URL du endpoint OAuth2
     */
    private function getAuthUrl(): string
    {
        return $this->isSandbox
            ? 'https://api.helloasso-sandbox.com/oauth2'
            : 'https://api.helloasso.com/oauth2';
    }
}
