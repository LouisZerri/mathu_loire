<?php

namespace App\Service;

use App\Entity\Representation;
use App\Entity\SeatAssignment;
use App\Repository\SeatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class SeatPlanPdfGenerator
{
    public function __construct(
        private Environment $twig,
        private SeatRepository $seatRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function generate(Representation $representation): string
    {
        $seats = $this->seatRepository->findAll();
        $assignments = $this->em->getRepository(SeatAssignment::class)
            ->findBy(['representation' => $representation]);

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
