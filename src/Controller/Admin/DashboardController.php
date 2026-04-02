<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function index(DashboardService $dashboardService): Response
    {
        $seasonStats = $dashboardService->getSeasonStats();
        $repStats = $dashboardService->getRepresentationStats();

        return $this->render('admin/dashboard.html.twig', [
            'seasonStats' => $seasonStats,
            'repStats' => $repStats,
        ]);
    }
}
