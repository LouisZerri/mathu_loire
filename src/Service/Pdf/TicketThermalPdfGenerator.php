<?php

namespace App\Service\Pdf;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF des billets au format thermique (80mm de large) pour impression sur imprimante ticket.
 */
class TicketThermalPdfGenerator
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * Génère le PDF thermique avec une hauteur dynamique selon le nombre de billets.
     *
     * @param Reservation $reservation Réservation pour laquelle générer les billets thermiques
     * @return string Contenu binaire du PDF généré
     */
    public function generate(Reservation $reservation): string
    {
        $html = $this->twig->render('pdf/ticket_thermal.html.twig', [
            'reservation' => $reservation,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // 80mm largeur, hauteur dynamique selon nb de billets (130mm par billet)
        $totalPlaces = $reservation->getNbAdults() + $reservation->getNbChildren() + $reservation->getNbInvitations() + $reservation->getNbGroups();
        $heightPerTicket = 368.5; // ~130mm en points
        $totalHeight = max($heightPerTicket, $totalPlaces * $heightPerTicket);
        $dompdf->setPaper([0, 0, 226.77, $totalHeight], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
