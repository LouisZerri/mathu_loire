<?php

/**
 * Audit N+1 complet du projet Les Mathu'Loire.
 * Usage: php tests/n1_check.php
 */

require __DIR__ . '/QueryAuditor.php';

$auditor = QueryAuditor::symfony(dirname(__DIR__));

// ============================================================
$auditor->section('Repositories');
// ============================================================

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

$auditor->test('ReservationRepo::findForReminder (+2 days)', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findForReminder(new \DateTime('+2 days'));
});

$auditor->test('ReservationRepo::findTodayReservations', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->findTodayReservations();
});

$auditor->test('ReservationRepo::countByFilters', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->countByFilters(null, null, 2027);
});

$auditor->test('RepresentationRepo::findUpcoming + loop show', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Representation::class)->findUpcoming();
    foreach ($r as $rep) { $rep->getShow()->getTitle(); }
    return $r;
});

$auditor->test('RepresentationRepo::findAvailableYears', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Representation::class)->findAvailableYears();
});

$auditor->test('RepresentationRepo::findByYear(2027) + loop', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Representation::class)->findByYear(2027);
    foreach ($r as $rep) { $rep->getShow()->getTitle(); }
    return $r;
});

$auditor->test('RepresentationRepo::findByMonth(2027, 1) + loop', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Representation::class)->findByMonth(2027, 1);
    foreach ($r as $rep) { $rep->getShow()->getTitle(); }
    return $r;
});

$auditor->test('RepresentationRepo::findBookedPlacesMap', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
});

$auditor->test('AuditLogRepo::findByFilters', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\AuditLog::class)->findByFilters(null, null, null, null, 1, 50);
});

$auditor->test('AuditLogRepo::countByFilters', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\AuditLog::class)->countByFilters(null, null, null, null);
});

$auditor->test('AuditLogRepo::findDistinctActions', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\AuditLog::class)->findDistinctActions();
});

$auditor->test('ShowRepo::findAll', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Show::class)->findAll();
});

$auditor->test('UserRepo::findAll', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\User::class)->findAll();
});

$auditor->test('SeatRepo::findAll', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Seat::class)->findAll();
});

// ============================================================
$auditor->section('SeatPlan API');
// ============================================================

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

// ============================================================
$auditor->section('Controllers publics');
// ============================================================

$auditor->test('Home: findUpcoming + findAll shows + loop', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findUpcoming();
    $shows = $a->repo(\App\Entity\Show::class)->findAll();
    foreach ($reps as $r) { $r->getShow()->getTitle(); }
    return $reps;
});

$auditor->test('ShowDetail: findByYear + bookedMap + loop', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findByYear(2027);
    $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
    foreach ($reps as $r) { $r->getShow()->getTitle(); }
    return $reps;
});

$auditor->test('Billetterie: findUpcoming + bookedMap + grouped', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findUpcoming();
    $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
    foreach ($reps as $r) { $r->getShow()->getTitle(); $r->getShow()->getAuthor(); }
    return $reps;
});

// ============================================================
$auditor->section('Controllers admin');
// ============================================================

$auditor->test('Admin résas index: filters + count + reps', function (QueryAuditor $a) {
    $a->repo(\App\Entity\Reservation::class)->findByFilters(null, null, 1, 20, 2027);
    $a->repo(\App\Entity\Reservation::class)->countByFilters(null, null, 2027);
    $a->repo(\App\Entity\Representation::class)->findByYear(2027);
    $a->repo(\App\Entity\Representation::class)->findAvailableYears();
    return [];
});

$auditor->test('Admin résas export: boucle complète', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Reservation::class)->findByFilters(null, null, 1, 10000, 2027);
    foreach ($r as $resa) {
        $rep = $resa->getRepresentation();
        $rep->getShow()->getTitle();
        $resa->getNbAdults() * (float) $rep->getAdultPrice();
    }
    return $r;
});

$auditor->test('Admin représentations: findByYear + loop', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findByYear(2027);
    foreach ($reps as $r) { $r->getShow()->getTitle(); }
    return $reps;
});

$auditor->test('Admin calendrier: findByMonth + bookedMap', function (QueryAuditor $a) {
    $reps = $a->repo(\App\Entity\Representation::class)->findByMonth(2027, 2);
    $a->repo(\App\Entity\Representation::class)->findBookedPlacesMap();
    foreach ($reps as $r) { $r->getShow()->getTitle(); }
    return $reps;
});

$auditor->test('Admin dashboard: stats complet', function (QueryAuditor $a) {
    $a->repo(\App\Entity\Reservation::class)->findSeasonStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findRepresentationStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findCityStats(2027);
    $a->repo(\App\Entity\Reservation::class)->findRevenueByShow(2027);
    $a->repo(\App\Entity\Representation::class)->findAvailableYears();
    return [];
}, 8);

$auditor->test('Admin audit: logs + users + actions', function (QueryAuditor $a) {
    $a->repo(\App\Entity\AuditLog::class)->findByFilters(null, null, null, null, 1, 50);
    $a->repo(\App\Entity\AuditLog::class)->countByFilters(null, null, null, null);
    $a->repo(\App\Entity\User::class)->findAll();
    $a->repo(\App\Entity\AuditLog::class)->findDistinctActions();
    return [];
}, 6);

// ============================================================
$auditor->section('PDF Generators');
// ============================================================

if ($activeRep) {
    $auditor->test('SessionReport: résas + seats + assignments', function (QueryAuditor $a) use ($activeRep) {
        $a->repo(\App\Entity\Reservation::class)->findBy(['representation' => $activeRep, 'status' => 'validated']);
        $a->repo(\App\Entity\Seat::class)->findAll();
        $assignments = $a->repo(\App\Entity\SeatAssignment::class)->findByRepresentationWithReservation($activeRep);
        foreach ($assignments as $sa) { $sa->getReservation()?->getSpectatorLastName(); }
        return $assignments;
    });

    $auditor->test('SeatPlanPdf: seats + assignments + loop', function (QueryAuditor $a) use ($activeRep) {
        $seats = $a->repo(\App\Entity\Seat::class)->findAll();
        $assignments = $a->repo(\App\Entity\SeatAssignment::class)->findByRepresentationWithReservation($activeRep);
        foreach ($assignments as $sa) { $sa->getReservation()?->getSpectatorLastName(); }
        foreach ($seats as $s) { $s->getRow(); }
        return $seats;
    });
}

// ============================================================
$auditor->section('Commandes');
// ============================================================

$auditor->test('SendReminders: findForReminder + loop', function (QueryAuditor $a) {
    $r = $a->repo(\App\Entity\Reservation::class)->findForReminder(new \DateTime('+2 days'));
    foreach ($r as $resa) { $resa->getRepresentation()->getShow()->getTitle(); }
    return $r;
});

// ============================================================
$auditor->section('Twig Extension');
// ============================================================

$auditor->test('AdminNotifications: countNewSince', function (QueryAuditor $a) {
    return $a->repo(\App\Entity\Reservation::class)->countNewSince(new \DateTimeImmutable('-1 day'));
});

// ============================================================
exit($auditor->report());
