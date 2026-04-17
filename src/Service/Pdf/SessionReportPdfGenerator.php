<?php

namespace App\Service\Pdf;

use App\Entity\Representation;
use App\Repository\ReservationRepository;
use App\Repository\SeatAssignmentRepository;
use App\Repository\SeatRepository;
use App\Service\Reservation\ReservationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF du rapport de séance avec la liste des réservations et les statistiques.
 */
class SessionReportPdfGenerator
{
    public function __construct(
        private Environment $twig,
        private ReservationRepository $reservationRepository,
        private SeatRepository $seatRepository,
        private SeatAssignmentRepository $seatAssignmentRepository,
        private ReservationService $reservationService,
    ) {
    }

    /**
     * Génère le PDF du rapport complet d'une séance (réservations, plan de salle, totaux).
     *
     * @param Representation $representation Représentation pour laquelle générer le rapport
     * @return string Contenu binaire du PDF généré
     */
    public function generate(Representation $representation): string
    {
        $reservations = $this->reservationRepository->findBy(
            ['representation' => $representation, 'status' => 'validated'],
            ['spectatorLastName' => 'ASC']
        );

        $seats = $this->seatRepository->findAll();
        $assignments = $this->seatAssignmentRepository->findByRepresentationWithReservation($representation);

        $assignmentMap = [];
        foreach ($assignments as $a) {
            $key = $a->getSeat()->getRow() . $a->getSeat()->getNumber();
            $assignmentMap[$key] = $a;
        }

        $totalAdults = 0;
        $totalChildren = 0;
        $totalInvitations = 0;
        $totalGroups = 0;
        $totalRevenue = 0;

        foreach ($reservations as $res) {
            $totalAdults += $res->getNbAdults();
            $totalChildren += $res->getNbChildren();
            $totalInvitations += $res->getNbInvitations();
            $totalGroups += $res->getNbGroups();
            $totalRevenue += $this->reservationService->computeTotal($res);
        }

        $totalSpectators = $totalAdults + $totalChildren + $totalInvitations;

        $cancelledReservations = $this->reservationRepository->findBy(
            ['representation' => $representation, 'status' => 'cancelled'],
            ['spectatorLastName' => 'ASC']
        );

        $totalRefunded = 0.0;
        foreach ($cancelledReservations as $res) {
            foreach ($res->getPayments() as $payment) {
                if ($payment->getType() === 'refund') {
                    $totalRefunded += abs((float) $payment->getAmount());
                }
            }
        }

        $html = $this->twig->render('pdf/session_report.html.twig', [
            'representation' => $representation,
            'reservations' => $reservations,
            'cancelledReservations' => $cancelledReservations,
            'assignmentMap' => $assignmentMap,
            'totalAdults' => $totalAdults,
            'totalChildren' => $totalChildren,
            'totalInvitations' => $totalInvitations,
            'totalGroups' => $totalGroups,
            'totalSpectators' => $totalSpectators,
            'totalRevenue' => $totalRevenue,
            'totalRefunded' => $totalRefunded,
            'totalReservations' => count($reservations),
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
