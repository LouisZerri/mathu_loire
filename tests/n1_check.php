<?php

require '/home/louis/Documents/DIVERS/QueryAuditor.php';

$auditor = QueryAuditor::symfony(dirname(__DIR__));

$auditor->section('Repositories');

$auditor->test('ReservationRepo::findByFilters (100 résas + loop)', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Reservation::class)->findByFilters(null, null, 1, 100, 2027);
    foreach ($r as $resa) { $resa->getRepresentation()->getShow()->getTitle(); }
    return $r;
});

$auditor->test('ReservationRepo::findSeasonStats(2027)', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findSeasonStats(2027);
});

$auditor->test('ReservationRepo::findRepresentationStats(2027)', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findRepresentationStats(2027);
});

$auditor->test('ReservationRepo::findCityStats(2027)', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findCityStats(2027);
});

$auditor->test('ReservationRepo::findRevenueByShow(2027)', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findRevenueByShow(2027);
});

$auditor->test('ReservationRepo::countNewSince', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->countNewSince(new \DateTimeImmutable('-7 days'));
});

$auditor->test('RepresentationRepo::findUpcoming + loop show', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Representation::class)->findUpcoming();
    foreach ($r as $rep) { $rep->getShow()->getTitle(); }
    return $r;
});

$auditor->test('RepresentationRepo::findByYear(2027) + loop', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Representation::class)->findByYear(2027);
    foreach ($r as $rep) { $rep->getShow()->getTitle(); }
    return $r;
});

$auditor->test('RepresentationRepo::findBookedPlacesMap', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
});

$auditor->section('SeatPlan');

$activeRep = $auditor->repo(\App\Entity\Representation::class)->findOneBy(['status' => 'active']);

if ($activeRep) {
    $auditor->test('SeatPlan: assignments + loop resa (JOIN FETCH)', function (QueryAuditor $a) use ($activeRep) {
        $assignments = $a->repo(\App\Entity\SeatAssignment::class)->findByRepresentationWithReservation($activeRep);
        foreach ($assignments as $sa) {
            $r = $sa->getReservation();
            if ($r) { $r->getSpectatorLastName(); }
            $sa->getSeat()->getRow();
        }
        return $assignments;
    });

    $auditor->test('SeatPlan: reservations + loop seatAssignments (JOIN FETCH)', function (QueryAuditor $a) use ($activeRep) {
        $reservations = $a->repo(\App\Entity\Reservation::class)->findByRepresentationWithAssignments($activeRep);
        foreach ($reservations as $r) {
            foreach ($r->getSeatAssignments() as $sa) { $sa->getSeat()->getRow(); }
        }
        return $reservations;
    });
}

$auditor->section('Controllers publics');

$auditor->test('Billetterie: findUpcoming + bookedMap + grouped', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findUpcoming();
    $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
    foreach ($reps as $r) { $r->getShow()->getTitle(); }
    return $reps;
});

$auditor->section('Controllers admin');

$auditor->test('Admin résas export: boucle complète', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Reservation::class)->findByFilters(null, null, 1, 10000, 2027);
    foreach ($r as $resa) {
        $rep = $resa->getRepresentation();
        $rep->getShow()->getTitle();
        $resa->getNbAdults() * (float) $rep->getAdultPrice();
        $resa->getNbGroups(); // nouveau champ
    }
    return $r;
});

$auditor->test('Admin dashboard: stats complet', function (QueryAuditor $a) {
    $a->repo(\App\Entity\Reservation::class)->findSeasonStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findRepresentationStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findCityStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findRevenueByShow(2027);
    $a->repo(\App\Entity\Representation::class)->findAvailableYears();
    return [];
}, 8);

$auditor->section('Paiements (ventilation)');

$auditor->test('Résa + loop payments (ventilation)', function (QueryAuditor $a) {
    $resas = $a->repo(\App\Entity\Reservation::class)->findByFilters(null, null, 1, 20, 2027);
    foreach ($resas as $r) {
        foreach ($r->getPayments() as $p) { $p->getMethod(); }
    }
    return $resas;
});

$auditor->section('PDF Generators');

if ($activeRep) {
    $auditor->test('SessionReport: résas + seats + assignments', function (QueryAuditor $a) use ($activeRep) {
        $a->repo(\App\Entity\Reservation::class)->findBy(['representation' => $activeRep, 'status' => 'validated']);
        $a->repo(\App\Entity\Seat::class)->findAll();
        $assignments = $a->repo(\App\Entity\SeatAssignment::class)->findByRepresentationWithReservation($activeRep);
        foreach ($assignments as $sa) { $sa->getReservation()?->getSpectatorLastName(); }
        return $assignments;
    });
}

$auditor->section('Commandes');

$auditor->test('SendReminders: findForReminder + loop', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Reservation::class)->findForReminder(new \DateTime('+2 days'));
    foreach ($r as $resa) { $resa->getRepresentation()->getShow()->getTitle(); }
    return $r;
});

$auditor->section('Twig Extension');

$auditor->test('AdminNotifications: countNewSince', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->countNewSince(new \DateTimeImmutable('-1 day'));
});

exit($auditor->report());
