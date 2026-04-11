<?php

namespace App\Controller;

use App\Repository\RepresentationRepository;
use App\Repository\ShowRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche la page d'accueil publique du site.
 */
class HomeController extends AbstractController
{
    /**
     * Affiche la page d'accueil avec les prochaines représentations et les spectacles.
     *
     * @return Response
     */
    #[Route('/', name: 'app_home')]
    public function index(
        RepresentationRepository $representationRepository,
        ShowRepository $showRepository,
    ): Response {
        $upcomingRepresentations = $representationRepository->findUpcoming();
        $shows = $showRepository->findAll();

        return $this->render('public/home.html.twig', [
            'representations' => $upcomingRepresentations,
            'shows' => $shows,
        ]);
    }
}
