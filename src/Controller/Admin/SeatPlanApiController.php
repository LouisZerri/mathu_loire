<?php

namespace App\Controller\Admin;

use App\Service\Admin\SeatPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fournit les endpoints JSON pour la gestion interactive du plan de salle (assignation, permutation, blocage).
 */
#[Route('/admin/plan-de-salle/api')]
#[IsGranted('ROLE_BILLETTISTE')]
class SeatPlanApiController extends AbstractController
{
    public function __construct(
        private readonly SeatPlanService $seatPlanService,
    ) {
    }

    /**
     * Parse le corps JSON de la requête et valide la présence des champs numériques requis.
     * @param Request $request La requête HTTP contenant le JSON
     * @param array $requiredFields Les noms des champs obligatoires
     * @return array|null Les données parsées ou null si invalides
     */
    private function parseJson(Request $request, array $requiredFields): ?array
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return null;
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field])) {
                return null;
            }
            $data[$field] = (int) $data[$field];
        }

        return $data;
    }

    /**
     * Assigne un siège à une réservation pour une représentation donnée.
     * @return JsonResponse
     */
    #[Route('/assign', name: 'app_admin_seatplan_api_assign', methods: ['POST'])]
    public function assign(Request $request): JsonResponse
    {
        $data = $this->parseJson($request, ['seatId', 'reservationId', 'representationId']);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        try {
            $this->seatPlanService->assignSeat($data['seatId'], $data['reservationId'], $data['representationId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Permute les réservations assignées entre deux sièges pour une représentation.
     * @return JsonResponse
     */
    #[Route('/swap', name: 'app_admin_seatplan_api_swap', methods: ['POST'])]
    public function swap(Request $request): JsonResponse
    {
        $data = $this->parseJson($request, ['seatAId', 'seatBId', 'representationId']);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        try {
            $this->seatPlanService->swapSeats($data['seatAId'], $data['seatBId'], $data['representationId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Supprime l'assignation d'un siège pour une représentation, le rendant à nouveau disponible.
     * @return JsonResponse
     */
    #[Route('/unassign', name: 'app_admin_seatplan_api_unassign', methods: ['POST'])]
    public function unassign(Request $request): JsonResponse
    {
        $data = $this->parseJson($request, ['seatId', 'representationId']);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        try {
            $this->seatPlanService->unassignSeat($data['seatId'], $data['representationId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Bloque un siège pour une représentation sans l'associer à une réservation.
     * @return JsonResponse
     */
    #[Route('/block', name: 'app_admin_seatplan_api_block', methods: ['POST'])]
    public function block(Request $request): JsonResponse
    {
        $data = $this->parseJson($request, ['seatId', 'representationId']);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        try {
            $this->seatPlanService->blockSeat($data['seatId'], $data['representationId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Bascule l'état actif/cassé d'un siège de manière globale.
     * @return JsonResponse
     */
    #[Route('/toggle-broken', name: 'app_admin_seatplan_api_toggle_broken', methods: ['POST'])]
    public function toggleBroken(Request $request): JsonResponse
    {
        $data = $this->parseJson($request, ['seatId']);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        try {
            $isActive = $this->seatPlanService->toggleBroken($data['seatId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true, 'isActive' => $isActive]);
    }
}
