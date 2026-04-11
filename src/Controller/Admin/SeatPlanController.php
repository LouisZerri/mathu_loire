<?php

namespace App\Controller\Admin;

use App\Repository\RepresentationRepository;
use App\Service\Admin\SeatPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Affiche le plan de salle interactif et fournit les données JSON des sièges et réservations.
 */
#[Route('/admin/plan-de-salle')]
#[IsGranted('ROLE_BILLETTISTE')]
class SeatPlanController extends AbstractController
{
    public function __construct(
        private readonly SeatPlanService $seatPlanService,
        private readonly RepresentationRepository $representationRepository,
    ) {
    }

    /**
     * Affiche la page du plan de salle interactif avec la liste des représentations actives.
     *
     * @return Response
     */
    #[Route('/', name: 'app_admin_seatplan')]
    public function index(Request $request): Response
    {
        $representations = $this->representationRepository->findBy(
            ['status' => 'active'],
            ['datetime' => 'ASC']
        );

        return $this->render('admin/seatplan/index.html.twig', [
            'representations' => $representations,
            'preselectedRepresentationId' => (int) $request->query->get('representation', 0) ?: null,
            'preselectedReservationId' => (int) $request->query->get('reservation', 0) ?: null,
        ]);
    }

    /**
     * Retourne en JSON la liste des sièges avec leur statut d'assignation pour une représentation.
     *
     * @param int $representationId L'identifiant de la représentation
     *
     * @return JsonResponse
     */
    #[Route('/api/seats/{representationId}', name: 'app_admin_seatplan_api_seats', methods: ['GET'])]
    public function getSeats(int $representationId): JsonResponse
    {
        $representation = $this->representationRepository->find($representationId);
        if (!$representation) {
            return $this->json(['error' => 'Représentation introuvable'], 404);
        }

        return $this->json($this->seatPlanService->getSeatData($representation));
    }

    /**
     * Retourne en JSON la liste des réservations avec le nombre de places assignées pour une représentation.
     *
     * @param int $representationId L'identifiant de la représentation
     *
     * @return JsonResponse
     */
    #[Route('/api/reservations/{representationId}', name: 'app_admin_seatplan_api_reservations', methods: ['GET'])]
    public function getReservations(int $representationId): JsonResponse
    {
        $representation = $this->representationRepository->find($representationId);
        if (!$representation) {
            return $this->json(['error' => 'Représentation introuvable'], 404);
        }

        return $this->json($this->seatPlanService->getReservationData($representation));
    }
}
