<?php

namespace App\Service\Admin;

use App\Entity\Representation;
use App\Entity\SeatAssignment;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Repository\SeatAssignmentRepository;
use App\Repository\SeatRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère la logique métier du plan de salle : construction des données,
 * assignation, permutation, blocage et gestion de l'état des sièges.
 */
class SeatPlanService
{
    public function __construct(
        private readonly SeatRepository $seatRepository,
        private readonly RepresentationRepository $representationRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly SeatAssignmentRepository $seatAssignmentRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Construit le tableau de données des sièges avec leur statut d'assignation pour une représentation.
     * @param Representation $representation La représentation concernée
     * @return array Liste des sièges avec statut, assignation et spectateur
     */
    public function getSeatData(Representation $representation): array
    {
        $seats = $this->seatRepository->findAll();
        $assignments = $this->seatAssignmentRepository->findByRepresentationWithReservation($representation);
        $assignmentMap = [];
        foreach ($assignments as $assignment) {
            $seat = $assignment->getSeat();
            $resa = $assignment->getReservation();
            $key = $seat->getRow() . $seat->getNumber();
            $assignmentMap[$key] = [
                'id' => $assignment->getId(),
                'status' => $assignment->getStatus(),
                'reservationId' => $resa?->getId(),
                'spectatorName' => $resa ? $resa->getSpectatorLastName() . ' ' . $resa->getSpectatorFirstName() : null,
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
            $seatData[] = [
                'id' => $seat->getId(), 'row' => $seat->getRow(), 'number' => $seat->getNumber(),
                'isActive' => $seat->isActive(), 'status' => $status,
                'assignmentId' => $a['id'] ?? null, 'reservationId' => $a['reservationId'] ?? null,
                'spectatorName' => $a['spectatorName'] ?? null,
            ];
        }
        return $seatData;
    }

    /**
     * Construit le tableau des réservations avec le nombre de places assignées pour une représentation.
     * @param Representation $representation La représentation concernée
     * @return array Liste des réservations avec compteurs de places
     */
    public function getReservationData(Representation $representation): array
    {
        $reservations = $this->reservationRepository->findByRepresentationWithAssignments($representation);
        $data = [];
        foreach ($reservations as $res) {
            $totalPlaces = $res->getNbAdults() + $res->getNbChildren() + $res->getNbInvitations();
            $assignedCount = $res->getSeatAssignments()->filter(
                fn(SeatAssignment $sa) => $sa->getStatus() === 'assigned'
            )->count();
            $data[] = [
                'id' => $res->getId(),
                'spectatorName' => $res->getSpectatorLastName() . ' ' . $res->getSpectatorFirstName(),
                'totalPlaces' => $totalPlaces, 'assignedCount' => $assignedCount, 'isPMR' => $res->isPMR(),
            ];
        }
        return $data;
    }

    /**
     * Assigne un siège à une réservation. Met à jour l'assignation existante le cas échéant.
     * @param int $seatId L'identifiant du siège
     * @param int $reservationId L'identifiant de la réservation
     * @param int $representationId L'identifiant de la représentation
     */
    public function assignSeat(int $seatId, int $reservationId, int $representationId): void
    {
        $seat = $this->seatRepository->find($seatId);
        $reservation = $this->reservationRepository->find($reservationId);
        $representation = $this->representationRepository->find($representationId);
        if (!$seat || !$reservation || !$representation) { throw new \InvalidArgumentException('Données invalides'); }
        $existing = $this->seatAssignmentRepository->findOneBy(['seat' => $seat, 'representation' => $representation]);
        if ($existing) {
            $existing->setReservation($reservation);
            $existing->setStatus('assigned');
        } else {
            $assignment = new SeatAssignment();
            $assignment->setSeat($seat);
            $assignment->setReservation($reservation);
            $assignment->setRepresentation($representation);
            $assignment->setStatus('assigned');
            $this->em->persist($assignment);
        }
        $this->em->flush();
    }

    /**
     * Permute les réservations entre deux sièges assignés pour une représentation.
     * @param int $seatAId L'identifiant du premier siège
     * @param int $seatBId L'identifiant du second siège
     * @param int $representationId L'identifiant de la représentation
     */
    public function swapSeats(int $seatAId, int $seatBId, int $representationId): void
    {
        $seatA = $this->seatRepository->find($seatAId);
        $seatB = $this->seatRepository->find($seatBId);
        $representation = $this->representationRepository->find($representationId);
        if (!$seatA || !$seatB || !$representation) { throw new \InvalidArgumentException('Données invalides'); }
        $assignA = $this->seatAssignmentRepository->findOneBy(['seat' => $seatA, 'representation' => $representation]);
        $assignB = $this->seatAssignmentRepository->findOneBy(['seat' => $seatB, 'representation' => $representation]);
        if (!$assignA || !$assignB || $assignA->getStatus() !== 'assigned' || $assignB->getStatus() !== 'assigned') {
            throw new \InvalidArgumentException('Les deux sièges doivent être assignés');
        }
        $reservationA = $assignA->getReservation();
        $assignA->setReservation($assignB->getReservation());
        $assignB->setReservation($reservationA);
        $this->em->flush();
    }

    /**
     * Supprime l'assignation d'un siège, le rendant à nouveau disponible.
     * @param int $seatId L'identifiant du siège
     * @param int $representationId L'identifiant de la représentation
     */
    public function unassignSeat(int $seatId, int $representationId): void
    {
        $seat = $this->seatRepository->find($seatId);
        $rep = $this->representationRepository->find($representationId);
        if (!$seat || !$rep) {
            throw new \InvalidArgumentException('Données invalides');
        }
        $assignment = $this->seatAssignmentRepository->findOneBy(['seat' => $seat, 'representation' => $rep]);
        if ($assignment) { $this->em->remove($assignment); $this->em->flush(); }
    }

    /**
     * Bloque un siège sans l'associer à une réservation (technique, PMR, etc.).
     * @param int $seatId L'identifiant du siège
     * @param int $representationId L'identifiant de la représentation
     */
    public function blockSeat(int $seatId, int $representationId): void
    {
        $seat = $this->seatRepository->find($seatId);
        $rep = $this->representationRepository->find($representationId);
        if (!$seat || !$rep) {
            throw new \InvalidArgumentException('Données invalides');
        }
        // Un siège bloqué = SeatAssignment sans reservation (réservé pour technique, PMR, etc.)
        $existing = $this->seatAssignmentRepository->findOneBy(['seat' => $seat, 'representation' => $rep]);
        if ($existing) {
            $existing->setReservation(null);
            $existing->setStatus('blocked');
        } else {
            $a = new SeatAssignment();
            $a->setSeat($seat); $a->setReservation(null); $a->setRepresentation($rep); $a->setStatus('blocked');
            $this->em->persist($a);
        }
        $this->em->flush();
    }

    /**
     * Bascule l'état actif/cassé d'un siège de manière globale.
     * @param int $seatId L'identifiant du siège
     * @return bool Le nouvel état isActive du siège
     */
    public function toggleBroken(int $seatId): bool
    {
        $seat = $this->seatRepository->find($seatId);
        if (!$seat) { throw new \InvalidArgumentException('Données invalides'); }
        $seat->setIsActive(!$seat->isActive());
        $this->em->flush();
        return $seat->isActive();
    }
}
