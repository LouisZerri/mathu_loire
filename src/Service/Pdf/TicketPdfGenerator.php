<?php

namespace App\Service\Pdf;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF du billet de réservation au format A4 paysage.
 */
class TicketPdfGenerator
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * Génère le PDF du billet pour une réservation donnée.
     *
     * @param Reservation $reservation Réservation pour laquelle générer le billet
     * @return string Contenu binaire du PDF généré
     */
    public function generate(Reservation $reservation): string
    {
        $html = $this->twig->render('pdf/ticket.html.twig', [
            'reservation' => $reservation,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
