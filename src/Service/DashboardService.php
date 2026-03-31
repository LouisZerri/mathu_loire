<?php

namespace App\Service;

use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;

class DashboardService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private RepresentationRepository $representationRepository,
    ) {
    }

    public function getSeasonStats(): array
    {
        $stats = $this->reservationRepository->findSeasonStats();

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

    public function getRepresentationStats(): array
    {
        $raw = $this->reservationRepository->findRepresentationStats();
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
