<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Form\AdminReservationType;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\HelloAssoPaymentHandler;
use App\Service\ReservationMailer;
use App\Service\ReservationService;
use App\Service\TicketThermalPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_admin_reservation_index')]
    public function index(
        Request $request,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
    ): Response {
        $repId = (int) $request->query->get('representation', 0);
        $status = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));

        $availableYears = $representationRepository->findAvailableYears();
        $currentYear = (int) date('Y');

        if (empty($availableYears)) {
            $availableYears = [$currentYear];
            $defaultYear = $currentYear;
        } else {
            $defaultYear = in_array($currentYear, $availableYears) ? $currentYear : $availableYears[0];
        }

        $selectedYear = (int) $request->query->get('year', $defaultYear);

        $representation = $repId ? $representationRepository->find($repId) : null;
        $statusFilter = $status ?: null;

        $reservations = $reservationRepository->findByFilters($representation, $statusFilter, $page, 20, $selectedYear);
        $totalReservations = $reservationRepository->countByFilters($representation, $statusFilter, $selectedYear);
        $totalPages = max(1, (int) ceil($totalReservations / 20));

        $representations = $representationRepository->findByYear($selectedYear);

        $cancelledReservations = $reservationRepository->findByFilters(null, 'cancelled', 1, 50, $selectedYear);

        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $reservations,
            'representations' => $representations,
            'currentRep' => $representation,
            'currentStatus' => $statusFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalReservations,
            'cancelledReservations' => $cancelledReservations,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_reservation_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
    ): Response {
        $form = $this->createForm(AdminReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUpdatedAt(new \DateTimeImmutable());
            $reservationService->save();

            $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' mise à jour.');

            return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
        }

        $total = $reservationService->computeTotal($reservation);

        return $this->render('admin/reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'total' => $total,
        ]);
    }

    #[Route('/{id}/resend-email', name: 'app_admin_reservation_resend_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendEmail(
        Reservation $reservation,
        ReservationMailer $mailer,
        Request $request,
    ): Response {
        if ($this->isCsrfTokenValid('resend_email_' . $reservation->getId(), $request->request->get('_token'))) {
            $mailer->sendConfirmation($reservation);
            $this->addFlash('success', 'Email de confirmation renvoyé à ' . $reservation->getSpectatorEmail() . '.');
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/refund', name: 'app_admin_reservation_refund', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refund(
        Reservation $reservation,
        Request $request,
        HelloAssoPaymentHandler $helloAssoHandler,
        ReservationService $reservationService,
        ReservationMailer $mailer,
    ): Response {
        if ($this->isCsrfTokenValid('refund_' . $reservation->getId(), $request->request->get('_token'))) {
            $refunded = $helloAssoHandler->refund($reservation);

            if ($refunded) {
                $reservationService->cancel($reservation);
                $mailer->sendCancellation($reservation);
                $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' annulée et remboursée.');
            } else {
                $this->addFlash('error', 'Le remboursement a échoué. Vérifiez les logs.');
            }
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/print', name: 'app_admin_reservation_print', requirements: ['id' => '\d+'])]
    public function print(
        Reservation $reservation,
        TicketThermalPdfGenerator $pdfGenerator,
    ): Response {
        $pdf = $pdfGenerator->generate($reservation);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="billets-thermal-%d.pdf"', $reservation->getId()),
        ]);
    }
}
