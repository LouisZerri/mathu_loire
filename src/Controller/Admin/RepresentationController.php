<?php

namespace App\Controller\Admin;

use App\Entity\Representation;
use App\Form\RepresentationType;
use App\Repository\RepresentationRepository;
use App\Service\SessionReportPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/representations')]
#[IsGranted('ROLE_ADMIN')]
class RepresentationController extends AbstractController
{
    #[Route('/', name: 'app_admin_representation_index')]
    public function index(Request $request, RepresentationRepository $representationRepository): Response
    {
        $availableYears = $representationRepository->findAvailableYears();
        $currentYear = (int) date('Y');

        if (empty($availableYears)) {
            $availableYears = [$currentYear];
            $defaultYear = $currentYear;
        } else {
            $defaultYear = in_array($currentYear, $availableYears) ? $currentYear : $availableYears[0];
        }

        $selectedYear = (int) $request->query->get('year', $defaultYear);

        $representations = $representationRepository->findByYear($selectedYear);

        $active = [];
        $cancelled = [];
        foreach ($representations as $rep) {
            if ($rep->getStatus() === 'cancelled') {
                $cancelled[] = $rep;
            } else {
                $active[] = $rep;
            }
        }

        return $this->render('admin/representation/index.html.twig', [
            'activeRepresentations' => $active,
            'cancelledRepresentations' => $cancelled,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

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

        // Regroupe par jour (clé = 'Y-m-d')
        $byDay = [];
        foreach ($representations as $rep) {
            $key = $rep->getDatetime()->format('Y-m-d');
            $byDay[$key][] = $rep;
        }

        $firstDay = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');
        // 0 = lundi, 6 = dimanche
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

    #[Route('/new', name: 'app_admin_representation_new')]
    public function new(Request $request, EntityManagerInterface $em, RepresentationRepository $representationRepository): Response
    {
        $representation = new Representation();

        $duplicateFrom = (int) $request->query->get('duplicate_from', 0);
        if ($duplicateFrom) {
            $source = $representationRepository->find($duplicateFrom);
            if ($source) {
                $representation->setShow($source->getShow());
                $representation->setDatetime((clone $source->getDatetime())->modify('+7 days'));
                $representation->setStatus('active');
                $representation->setMaxOnlineReservations($source->getMaxOnlineReservations());
                $representation->setVenueCapacity($source->getVenueCapacity());
                $representation->setAdultPrice($source->getAdultPrice());
                $representation->setChildPrice($source->getChildPrice());
                $representation->setGroupPrice($source->getGroupPrice());
            }
        }

        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($representation);
            $em->flush();

            $this->addFlash('success', 'Représentation créée.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_representation_edit', requirements: ['id' => '\d+'])]
    public function edit(Representation $representation, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Représentation mise à jour.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => false,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_representation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Representation $representation, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('cancel_rep_' . $representation->getId(), $request->request->get('_token'))) {
            $representation->setStatus('cancelled');
            $em->flush();
            $this->addFlash('success', 'Représentation annulée.');
        }

        return $this->redirectToRoute('app_admin_representation_index');
    }

    #[Route('/{id}/duplicate', name: 'app_admin_representation_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Representation $representation, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('duplicate_rep_' . $representation->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->redirectToRoute('app_admin_representation_new', [
            'duplicate_from' => $representation->getId(),
        ]);
    }

    #[Route('/{id}/releve', name: 'app_admin_representation_report', requirements: ['id' => '\d+'])]
    public function report(Representation $representation, SessionReportPdfGenerator $pdfGenerator): Response
    {
        $pdf = $pdfGenerator->generate($representation);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="releve-seance-%d.pdf"', $representation->getId()),
        ]);
    }
}
