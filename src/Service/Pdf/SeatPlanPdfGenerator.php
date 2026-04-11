<?php

namespace App\Service\Pdf;

use App\Entity\Representation;
use App\Repository\SeatAssignmentRepository;
use App\Repository\SeatRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF du plan de salle pour une représentation avec l'état de chaque siège.
 */
class SeatPlanPdfGenerator
{
    public function __construct(
        private Environment $twig,
        private SeatRepository $seatRepository,
        private SeatAssignmentRepository $seatAssignmentRepository,
    ) {
    }

    /**
     * Génère le PDF du plan de salle en format paysage A4.
     *
     * @param Representation $representation Représentation pour laquelle générer le plan
     * @return string Contenu binaire du PDF généré
     */
    public function generate(Representation $representation): string
    {
        $seats = $this->seatRepository->findAll();
        $assignments = $this->seatAssignmentRepository->findByRepresentationWithReservation($representation);

        $assignmentMap = [];
        foreach ($assignments as $a) {
            $key = $a->getSeat()->getRow() . $a->getSeat()->getNumber();
            $assignmentMap[$key] = [
                'status' => $a->getStatus(),
                'spectatorName' => $a->getReservation()
                    ? $a->getReservation()->getSpectatorLastName()
                    : null,
            ];
        }

        $seatData = [];
        foreach ($seats as $seat) {
            $key = $seat->getRow() . $seat->getNumber();
            $a = $assignmentMap[$key] ?? null;

            $status = 'available';
            if (!$seat->isActive()) {
                $status = 'broken';
            } elseif ($a && $a['status'] === 'blocked') {
                $status = 'blocked';
            } elseif ($a && $a['status'] === 'assigned') {
                $status = 'assigned';
            }

            $seatData[$key] = [
                'row' => $seat->getRow(),
                'number' => $seat->getNumber(),
                'status' => $status,
                'spectatorName' => $a['spectatorName'] ?? null,
            ];
        }

        $html = $this->twig->render('pdf/seatplan.html.twig', [
            'representation' => $representation,
            'seatData' => $seatData,
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
