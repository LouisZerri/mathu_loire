<?php

namespace App\Controller\Admin;

use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function index(
        Request $request,
        DashboardService $dashboardService,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
    ): Response {
        $seasonStats = $dashboardService->getSeasonStats();
        $repStats = $dashboardService->getRepresentationStats();

        // Filtres réservations
        $repId = (int) $request->query->get('representation', 0);
        $status = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));

        $representation = $repId ? $representationRepository->find($repId) : null;
        $statusFilter = $status ?: null;

        $reservations = $reservationRepository->findByFilters($representation, $statusFilter, $page);
        $totalReservations = $reservationRepository->countByFilters($representation, $statusFilter);
        $totalPages = max(1, (int) ceil($totalReservations / 20));

        $representations = $representationRepository->findBy([], ['datetime' => 'ASC']);

        return $this->render('admin/dashboard.html.twig', [
            'seasonStats' => $seasonStats,
            'repStats' => $repStats,
            'reservations' => $reservations,
            'representations' => $representations,
            'currentRep' => $representation,
            'currentStatus' => $statusFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalReservations,
        ]);
    }
}
