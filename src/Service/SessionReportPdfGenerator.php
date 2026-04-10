<?php

namespace App\Service;

use App\Entity\Representation;
use App\Repository\ReservationRepository;
use App\Repository\SeatAssignmentRepository;
use App\Repository\SeatRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

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
        $totalRevenue = 0;

        foreach ($reservations as $res) {
            $totalAdults += $res->getNbAdults();
            $totalChildren += $res->getNbChildren();
            $totalInvitations += $res->getNbInvitations();
            $totalRevenue += $this->reservationService->computeTotal($res);
        }

        $totalSpectators = $totalAdults + $totalChildren + $totalInvitations;

        $html = $this->twig->render('pdf/session_report.html.twig', [
            'representation' => $representation,
            'reservations' => $reservations,
            'assignmentMap' => $assignmentMap,
            'totalAdults' => $totalAdults,
            'totalChildren' => $totalChildren,
            'totalInvitations' => $totalInvitations,
            'totalSpectators' => $totalSpectators,
            'totalRevenue' => $totalRevenue,
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
