<?php

namespace App\Service\HelloAsso;

/**
 * Implémentation factice du client HelloAsso pour les tests E2E.
 *
 * Court-circuite le checkout : redirige immédiatement vers returnUrl, simule un paiement
 * "Authorized" et accepte tous les remboursements. Activé uniquement en APP_ENV=test_e2e.
 */
class HelloAssoClientFake extends HelloAssoClient
{
    public const FAKE_CHECKOUT_ID = 999_999;
    public const FAKE_PAYMENT_ID = 'fake-payment-id';

    public function __construct()
    {
        // Pas d'appel au parent : on n'a besoin d'aucune dépendance HTTP/log.
    }

    public function createCheckoutIntent(array $payload): array
    {
        return [
            'id' => self::FAKE_CHECKOUT_ID,
            'redirectUrl' => $payload['returnUrl'] ?? '/',
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function getCheckoutIntent(int $checkoutIntentId): array
    {
        return [
            'id' => $checkoutIntentId,
            'order' => [
                'payments' => [
                    [
                        'id' => self::FAKE_PAYMENT_ID,
                        'state' => 'Authorized',
                        'amount' => 900,
                    ],
                ],
            ],
        ];
    }

    public function getPayment(string $paymentId): ?array
    {
        return [
            'id' => $paymentId,
            'state' => 'Authorized',
            'amount' => 900,
        ];
    }

    public function refundPayment(string $transactionId): bool
    {
        return true;
    }
}
