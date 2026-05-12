<?php

namespace App\Service\Pdf;

use App\Entity\Reservation;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Génère le QR code d'un billet individuel.
 *
 * Le QR encode une URL absolue signée HMAC qui pointe vers la page de scan admin.
 * Sans la clé secrète serveur, il est impossible de forger un QR valide.
 */
class TicketQrCodeGenerator
{
    private const HMAC_LENGTH = 16;

    public function __construct(
        #[Autowire('%env(APP_BASE_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(APP_SECRET)%')]
        private string $appSecret,
    ) {
    }

    /**
     * Retourne le QR code en data URI PNG, prêt à être inséré dans un <img src="...">.
     */
    public function generateDataUri(Reservation $reservation, int $ticketIndex): string
    {
        $url = $this->buildScanUrl($reservation, $ticketIndex);

        $qrCode = new QrCode(data: $url, size: 150, margin: 2);

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    /**
     * Construit l'URL de scan signée pour un billet. Exposé pour les tests.
     */
    public function buildScanUrl(Reservation $reservation, int $ticketIndex): string
    {
        $tag = $reservation->getId() . '-' . $ticketIndex;

        return rtrim($this->baseUrl, '/') . '/admin/scan/' . $tag . '/' . $this->sign($tag);
    }

    /**
     * Vérifie qu'un HMAC fourni correspond bien au tag attendu.
     */
    public function isValidHmac(string $tag, string $hmac): bool
    {
        return hash_equals($this->sign($tag), $hmac);
    }

    private function sign(string $tag): string
    {
        return substr(hash_hmac('sha256', $tag, $this->appSecret), 0, self::HMAC_LENGTH);
    }
}
