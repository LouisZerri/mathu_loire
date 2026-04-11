<?php

namespace App\Controller\Admin;

use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\Admin\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche le tableau de bord principal de l'espace d'administration.
 */
#[Route('/admin')]
#[IsGranted('ROLE_BILLETTISTE')]
class DashboardController extends AbstractController
{
    /**
     * Présente les statistiques de la saison sélectionnée : revenus, taux de remplissage et répartition par ville.
     *
     * @return Response
     */
    #[Route('/', name: 'app_admin_dashboard')]
    public function index(
        Request $request,
        DashboardService $dashboardService,
        RepresentationRepository $representationRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $availableYears = $representationRepository->findAvailableYears();
        $currentYear = (int) date('Y');

        if (empty($availableYears)) {
            $availableYears = [$currentYear];
            $defaultYear = $currentYear;
        } else {
            $defaultYear = in_array($currentYear, $availableYears) ? $currentYear : $availableYears[0];
        }

        $selectedYear = (int) $request->query->get('year', $defaultYear);

        $seasonStats = $dashboardService->getSeasonStats($selectedYear);
        $repStats = $dashboardService->getRepresentationStats($selectedYear);
        $cityStats = $reservationRepository->findCityStats($selectedYear);
        $revenueByShow = $reservationRepository->findRevenueByShow($selectedYear);

        return $this->render('admin/dashboard.html.twig', [
            'seasonStats' => $seasonStats,
            'repStats' => $repStats,
            'cityStats' => $cityStats,
            'revenueByShow' => $revenueByShow,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }
}
