<?php

namespace App\Controller\Admin;

use App\Repository\RepresentationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Affiche les représentations sous forme de calendrier mensuel dans l'administration.
 */
#[Route('/admin/representations')]
#[IsGranted('ROLE_ADMIN')]
class RepresentationCalendarController extends AbstractController
{
    /**
     * Affiche le calendrier mensuel des représentations avec navigation mois par mois.
     *
     * @return Response
     */
    #[Route('/calendar', name: 'app_admin_representation_calendar')]
    public function calendar(Request $request, RepresentationRepository $representationRepository): Response
    {
        $now = new \DateTime();
        $year = (int) $request->query->get('year', (int) $now->format('Y'));
        $month = (int) $request->query->get('month', (int) $now->format('n'));

        if ($month < 1 || $month > 12) {
            $month = (int) $now->format('n');
        }

        $representations = $representationRepository->findByMonth($year, $month);
        $bookedMap = $representationRepository->findBookedPlacesMap();

        $byDay = [];
        foreach ($representations as $rep) {
            $key = $rep->getDatetime()->format('Y-m-d');
            $byDay[$key][] = $rep;
        }

        $firstDay = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');
        $startOffset = ((int) $firstDay->format('N')) - 1;

        $prev = (clone $firstDay)->modify('-1 month');
        $next = (clone $firstDay)->modify('+1 month');

        return $this->render('admin/representation/calendar.html.twig', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'LLLL yyyy'))->format($firstDay),
            'daysInMonth' => $daysInMonth,
            'startOffset' => $startOffset,
            'byDay' => $byDay,
            'bookedMap' => $bookedMap,
            'prevYear' => (int) $prev->format('Y'),
            'prevMonth' => (int) $prev->format('n'),
            'nextYear' => (int) $next->format('Y'),
            'nextMonth' => (int) $next->format('n'),
            'today' => $now->format('Y-m-d'),
        ]);
    }
}
