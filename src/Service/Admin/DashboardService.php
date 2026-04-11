<?php

namespace App\Service\Admin;

use App\Repository\ReservationRepository;

/**
 * Agrège les statistiques de réservation pour le tableau de bord admin.
 */
class DashboardService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Retourne les totaux globaux (spectateurs, recettes) pour une saison.
     *
     * @param int|null $year Année de la saison (null = saison courante)
     * @return array Statistiques agrégées (totalReservations, totalSpectators, totalRevenue, etc.)
     */
    public function getSeasonStats(?int $year = null): array
    {
        $stats = $this->reservationRepository->findSeasonStats($year);

        $totalAdults = (int) ($stats['totalAdults'] ?? 0);
        $totalChildren = (int) ($stats['totalChildren'] ?? 0);
        $totalInvitations = (int) ($stats['totalInvitations'] ?? 0);
        $totalSpectators = $totalAdults + $totalChildren + $totalInvitations;

        return [
            'totalReservations' => (int) ($stats['totalReservations'] ?? 0),
            'totalSpectators' => $totalSpectators,
            'totalAdults' => $totalAdults,
            'totalChildren' => $totalChildren,
            'totalInvitations' => $totalInvitations,
            'totalRevenue' => (float) ($stats['totalRevenue'] ?? 0),
        ];
    }

    /**
     * Retourne les statistiques détaillées par représentation (remplissage, recettes).
     *
     * @param int|null $year Année de la saison (null = saison courante)
     * @return array Liste des statistiques par représentation
     */
    public function getRepresentationStats(?int $year = null): array
    {
        $raw = $this->reservationRepository->findRepresentationStats($year);
        $stats = [];

        foreach ($raw as $row) {
            $totalPlaces = (int) $row['totalAdults'] + (int) $row['totalChildren'] + (int) $row['totalInvitations'];
            $capacity = (int) $row['venueCapacity'];
            $fillRate = $capacity > 0 ? round(($totalPlaces / $capacity) * 100) : 0;

            $stats[] = [
                'id' => $row['repId'],
                'showTitle' => $row['showTitle'],
                'datetime' => $row['datetime'],
                'status' => $row['repStatus'],
                'venueCapacity' => $capacity,
                'maxOnline' => (int) $row['maxOnline'],
                'totalPlaces' => $totalPlaces,
                'totalAdults' => (int) $row['totalAdults'],
                'totalChildren' => (int) $row['totalChildren'],
                'totalInvitations' => (int) $row['totalInvitations'],
                'totalReservations' => (int) $row['totalReservations'],
                'revenue' => (float) $row['revenue'],
                'fillRate' => $fillRate,
            ];
        }

        return $stats;
    }
}
