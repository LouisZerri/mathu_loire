<?php

namespace App\Controller\Admin;

use App\Entity\SeatAssignment;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Repository\SeatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plan-de-salle')]
#[IsGranted('ROLE_BILLETTISTE')]
class SeatPlanController extends AbstractController
{
    #[Route('/', name: 'app_admin_seatplan')]
    public function index(Request $request, RepresentationRepository $representationRepository): Response
    {
        $representations = $representationRepository->findBy(
            ['status' => 'active'],
            ['datetime' => 'ASC']
        );

        $preselectedRepresentationId = (int) $request->query->get('representation', 0) ?: null;
        $preselectedReservationId = (int) $request->query->get('reservation', 0) ?: null;

        return $this->render('admin/seatplan/index.html.twig', [
            'representations' => $representations,
            'preselectedRepresentationId' => $preselectedRepresentationId,
            'preselectedReservationId' => $preselectedReservationId,
        ]);
    }

    #[Route('/api/seats/{representationId}', name: 'app_admin_seatplan_api_seats', methods: ['GET'])]
    public function getSeats(
        int $representationId,
        SeatRepository $seatRepository,
        RepresentationRepository $representationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $representation = $representationRepository->find($representationId);
        if (!$representation) {
            return $this->json(['error' => 'Représentation introuvable'], 404);
        }

        $seats = $seatRepository->findAll();
        $assignments = $em->getRepository(SeatAssignment::class)->findBy(['representation' => $representation]);

        $assignmentMap = [];
        foreach ($assignments as $assignment) {
            $key = $assignment->getSeat()->getRow() . $assignment->getSeat()->getNumber();
            $assignmentMap[$key] = [
                'id' => $assignment->getId(),
                'status' => $assignment->getStatus(),
                'reservationId' => $assignment->getReservation()?->getId(),
                'spectatorName' => $assignment->getReservation()
                    ? $assignment->getReservation()->getSpectatorLastName() . ' ' . $assignment->getReservation()->getSpectatorFirstName()
                    : null,
            ];
        }

        $seatData = [];
        foreach ($seats as $seat) {
            $key = $seat->getRow() . $seat->getNumber();
            $assignment = $assignmentMap[$key] ?? null;

            $status = 'available';
            if (!$seat->isActive()) {
                $status = 'broken';
            } elseif ($assignment && $assignment['status'] === 'blocked') {
                $status = 'blocked';
            } elseif ($assignment && $assignment['status'] === 'assigned') {
                $status = 'assigned';
            }

            $seatData[] = [
                'id' => $seat->getId(),
                'row' => $seat->getRow(),
                'number' => $seat->getNumber(),
                'isActive' => $seat->isActive(),
                'status' => $status,
                'assignmentId' => $assignment['id'] ?? null,
                'reservationId' => $assignment['reservationId'] ?? null,
                'spectatorName' => $assignment['spectatorName'] ?? null,
            ];
        }

        return $this->json($seatData);
    }

    #[Route('/api/reservations/{representationId}', name: 'app_admin_seatplan_api_reservations', methods: ['GET'])]
    public function getReservations(
        int $representationId,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
    ): JsonResponse {
        $representation = $representationRepository->find($representationId);
        if (!$representation) {
            return $this->json(['error' => 'Représentation introuvable'], 404);
        }

        $reservations = $reservationRepository->findBy([
            'representation' => $representation,
            'status' => 'validated',
        ], ['spectatorLastName' => 'ASC']);

        $data = [];
        foreach ($reservations as $res) {
            $totalPlaces = $res->getNbAdults() + $res->getNbChildren() + $res->getNbInvitations();
            $assignedCount = $res->getSeatAssignments()->filter(
                fn(SeatAssignment $sa) => $sa->getStatus() === 'assigned'
            )->count();

            $data[] = [
                'id' => $res->getId(),
                'spectatorName' => $res->getSpectatorLastName() . ' ' . $res->getSpectatorFirstName(),
                'totalPlaces' => $totalPlaces,
                'assignedCount' => $assignedCount,
                'isPMR' => $res->isPMR(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/assign', name: 'app_admin_seatplan_api_assign', methods: ['POST'])]
    public function assignSeat(
        Request $request,
        SeatRepository $seatRepository,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $seat = $seatRepository->find($data['seatId'] ?? 0);
        $reservation = $reservationRepository->find($data['reservationId'] ?? 0);
        $representation = $representationRepository->find($data['representationId'] ?? 0);

        if (!$seat || !$reservation || !$representation) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $existing = $em->getRepository(SeatAssignment::class)->findOneBy([
            'seat' => $seat,
            'representation' => $representation,
        ]);

        if ($existing) {
            $existing->setReservation($reservation);
            $existing->setStatus('assigned');
        } else {
            $assignment = new SeatAssignment();
            $assignment->setSeat($seat);
            $assignment->setReservation($reservation);
            $assignment->setRepresentation($representation);
            $assignment->setStatus('assigned');
            $em->persist($assignment);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/swap', name: 'app_admin_seatplan_api_swap', methods: ['POST'])]
    public function swapSeats(
        Request $request,
        SeatRepository $seatRepository,
        RepresentationRepository $representationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $seatA = $seatRepository->find($data['seatAId'] ?? 0);
        $seatB = $seatRepository->find($data['seatBId'] ?? 0);
        $representation = $representationRepository->find($data['representationId'] ?? 0);

        if (!$seatA || !$seatB || !$representation) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $assignA = $em->getRepository(SeatAssignment::class)->findOneBy([
            'seat' => $seatA,
            'representation' => $representation,
        ]);
        $assignB = $em->getRepository(SeatAssignment::class)->findOneBy([
            'seat' => $seatB,
            'representation' => $representation,
        ]);

        if (!$assignA || !$assignB || $assignA->getStatus() !== 'assigned' || $assignB->getStatus() !== 'assigned') {
            return $this->json(['error' => 'Les deux sièges doivent être assignés'], 400);
        }

        $reservationA = $assignA->getReservation();
        $reservationB = $assignB->getReservation();

        $assignA->setReservation($reservationB);
        $assignB->setReservation($reservationA);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/unassign', name: 'app_admin_seatplan_api_unassign', methods: ['POST'])]
    public function unassignSeat(
        Request $request,
        SeatRepository $seatRepository,
        RepresentationRepository $representationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $seat = $seatRepository->find($data['seatId'] ?? 0);
        $representation = $representationRepository->find($data['representationId'] ?? 0);

        if (!$seat || !$representation) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $assignment = $em->getRepository(SeatAssignment::class)->findOneBy([
            'seat' => $seat,
            'representation' => $representation,
        ]);

        if ($assignment) {
            $em->remove($assignment);
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/block', name: 'app_admin_seatplan_api_block', methods: ['POST'])]
    public function blockSeat(
        Request $request,
        SeatRepository $seatRepository,
        RepresentationRepository $representationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $seat = $seatRepository->find($data['seatId'] ?? 0);
        $representation = $representationRepository->find($data['representationId'] ?? 0);

        if (!$seat || !$representation) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $existing = $em->getRepository(SeatAssignment::class)->findOneBy([
            'seat' => $seat,
            'representation' => $representation,
        ]);

        if ($existing) {
            $existing->setReservation(null);
            $existing->setStatus('blocked');
        } else {
            $assignment = new SeatAssignment();
            $assignment->setSeat($seat);
            $assignment->setReservation(null);
            $assignment->setRepresentation($representation);
            $assignment->setStatus('blocked');
            $em->persist($assignment);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/toggle-broken', name: 'app_admin_seatplan_api_toggle_broken', methods: ['POST'])]
    public function toggleBroken(
        Request $request,
        SeatRepository $seatRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $seat = $seatRepository->find($data['seatId'] ?? 0);

        if (!$seat) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        $seat->setIsActive(!$seat->isActive());
        $em->flush();

        return $this->json(['success' => true, 'isActive' => $seat->isActive()]);
    }
}
