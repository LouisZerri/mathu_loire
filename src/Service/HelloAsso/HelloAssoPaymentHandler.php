<?php

namespace App\Service\HelloAsso;

use App\Entity\Payment;
use App\Entity\Representation;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Orchestre le cycle de paiement HelloAsso : création checkout, vérification, enregistrement et remboursement.
 */
class HelloAssoPaymentHandler
{
    public function __construct(
        #[Autowire('%env(APP_BASE_URL)%')]
        private string $baseUrl,
        private HelloAssoClient $client,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Construit et envoie l'intention de checkout HelloAsso depuis un brouillon de réservation.
     *
     * @param array $draft Données du formulaire (firstName, lastName, email, etc.)
     * @param Representation $representation Représentation concernée
     * @param float $total Montant total en euros
     * @param string $draftToken Jeton unique associé au brouillon
     * @return array Réponse de l'API HelloAsso (id, redirectUrl, etc.)
     */
    public function createCheckoutIntentFromDraft(array $draft, Representation $representation, float $total, string $draftToken): array
    {
        $backUrl = $this->baseUrl . $this->urlGenerator->generate('app_reservation_cancel', ['id' => $representation->getId()]);
        $returnUrl = $this->baseUrl . $this->urlGenerator->generate('app_reservation_return', ['id' => $representation->getId()]);

        $this->logger->info('HelloAsso URLs - back: {back} return: {return}', ['back' => $backUrl, 'return' => $returnUrl]);

        return $this->client->createCheckoutIntent([
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
        ]);
    }

    /**
     * Vérifie qu'un checkout a été payé et retourne les données de paiement si autorisé.
     *
     * @param int $checkoutIntentId Identifiant de l'intention de checkout
     * @return array|null Données du paiement ou null si non autorisé
     */
    public function verifyCheckout(int $checkoutIntentId): ?array
    {
        $data = $this->client->getCheckoutIntent($checkoutIntentId);

        if (!isset($data['order']['payments'][0])) {
            return null;
        }

        $paymentData = $data['order']['payments'][0];

        return ($paymentData['state'] ?? '') === 'Authorized' ? $paymentData : null;
    }

    /**
     * Confirme qu'un paiement existe et est autorisé sur HelloAsso.
     *
     * @param string $paymentId Identifiant du paiement à vérifier
     * @return bool Vrai si le paiement est autorisé
     */
    public function verifyPaymentExists(string $paymentId): bool
    {
        $data = $this->client->getPayment($paymentId);

        return $data !== null && ($data['state'] ?? '') === 'Authorized';
    }

    /**
     * Persiste un enregistrement Payment en BDD depuis la réponse HelloAsso.
     *
     * @param Reservation $reservation Réservation associée au paiement
     * @param array $paymentData Réponse paiement HelloAsso (id, amount en centimes, etc.)
     * @return void
     */
    public function recordPayment(Reservation $reservation, array $paymentData): void
    {
        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setMethod('helloasso');
        $payment->setAmount((string) ($paymentData['amount'] / 100)); // HelloAsso retourne les montants en centimes
        $payment->setType('payment');
        $payment->setTransactionId((string) $paymentData['id']);
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();
    }

    /**
     * Rembourse le paiement HelloAsso lié à la réservation et enregistre l'écriture inverse.
     *
     * @param Reservation $reservation Réservation à rembourser
     * @return bool Vrai si le remboursement a réussi
     */
    public function refund(Reservation $reservation): bool
    {
        foreach ($reservation->getPayments() as $payment) {
            if ($payment->getMethod() !== 'helloasso' || $payment->getType() !== 'payment' || !$payment->getTransactionId()) {
                continue;
            }

            if ($this->client->refundPayment($payment->getTransactionId())) {
                $refundPayment = new Payment();
                $refundPayment->setReservation($reservation);
                $refundPayment->setMethod('helloasso');
                // Montant négatif = remboursement. abs() empêche un double-négatif si amount est déjà négatif
                $refundPayment->setAmount((string) -abs((float) $payment->getAmount()));
                $refundPayment->setType('refund');
                $refundPayment->setTransactionId($payment->getTransactionId() . '_refund');
                $refundPayment->setCreatedAt(new \DateTimeImmutable());

                $this->em->persist($refundPayment);

                return true;
            }
        }

        return false;
    }

    /**
     * Traite un webhook HelloAsso et extrait les informations de paiement.
     *
     * @param array $data Payload brut du webhook HelloAsso
     * @return array|null Données extraites ou null si le webhook n'est pas un paiement valide
     */
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

        return [
            'representation_id' => (int) $representationId,
            'draft_token' => $metadata['draft_token'] ?? null,
            'amount' => ($paymentInfo['amount'] ?? 0) / 100,
            'transaction_id' => (string) ($paymentInfo['id'] ?? ''),
            'payer' => $paymentInfo['payer'] ?? [],
        ];
    }
}
