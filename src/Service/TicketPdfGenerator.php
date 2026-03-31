<?php

namespace App\Service;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class TicketPdfGenerator
{
    public function __construct(
        private Environment $twig,
    ) {
    }

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
