<?php

namespace App\Controller;

use App\Repository\RepresentationRepository;
use App\Repository\ShowRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
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
