<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\AdminReservationType;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\AuditLogger;
use App\Service\HelloAssoPaymentHandler;
use App\Service\ReservationMailer;
use App\Service\ReservationService;
use App\Service\TicketThermalPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
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
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLastReservationsViewedAt(new \DateTimeImmutable());
            $em->flush();
        }

        $repId = (int) $request->query->get('representation', 0);
        $status = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));
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
        $searchFilter = $search !== '' ? $search : null;

        // Si recherche, on ignore le filtre année pour chercher sur toute l'histoire
        $yearFilter = $searchFilter ? null : $selectedYear;

        $reservations = $reservationRepository->findByFilters($representation, $statusFilter, $page, 20, $yearFilter, $searchFilter);
        $totalReservations = $reservationRepository->countByFilters($representation, $statusFilter, $yearFilter, $searchFilter);
        $totalPages = max(1, (int) ceil($totalReservations / 20));

        $representations = $representationRepository->findByYear($selectedYear);

        $cancelledReservations = $searchFilter ? [] : $reservationRepository->findByFilters(null, 'cancelled', 1, 50, $selectedYear);

        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $reservations,
            'representations' => $representations,
            'currentRep' => $representation,
            'currentStatus' => $statusFilter,
            'currentSearch' => $searchFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalReservations,
            'cancelledReservations' => $cancelledReservations,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    #[Route('/export', name: 'app_admin_reservation_export')]
    public function export(
        Request $request,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
    ): Response {
        $repId = (int) $request->query->get('representation', 0);
        $status = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));
        $year = (int) $request->query->get('year', 0) ?: null;

        $representation = $repId ? $representationRepository->find($repId) : null;
        $statusFilter = $status ?: null;
        $searchFilter = $search !== '' ? $search : null;
        $yearFilter = $searchFilter ? null : $year;

        $reservations = $reservationRepository->findByFilters($representation, $statusFilter, 1, 10000, $yearFilter, $searchFilter);

        $csv = "N°;Statut;Nom;Prénom;Ville;Téléphone;Email;Spectacle;Date;Adultes;Enfants;Invitations;PMR;Total;Enregistrée le\n";

        foreach ($reservations as $r) {
            $rep = $r->getRepresentation();
            $total = ($r->getNbAdults() * (float) $rep->getAdultPrice()) + ($r->getNbChildren() * (float) $rep->getChildPrice());

            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%s;%s;%s;%d;%d;%d;%s;%s;%s\n",
                $r->getId(),
                $r->getStatus(),
                str_replace(';', ',', $r->getSpectatorLastName()),
                str_replace(';', ',', $r->getSpectatorFirstName()),
                str_replace(';', ',', $r->getSpectatorCity()),
                $r->getSpectatorPhone(),
                $r->getSpectatorEmail(),
                str_replace(';', ',', $rep->getShow()->getTitle()),
                $rep->getDatetime()->format('d/m/Y H:i'),
                $r->getNbAdults(),
                $r->getNbChildren(),
                $r->getNbInvitations(),
                $r->isPMR() ? 'Oui' : 'Non',
                number_format($total, 2, '.', ''),
                $r->getCreatedAt()->format('d/m/Y H:i'),
            );
        }

        return new Response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservations-' . date('Y-m-d') . '.csv"',
        ]);
    }

    #[Route('/new', name: 'app_admin_reservation_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        $reservation = new Reservation();
        $form = $this->createForm(AdminReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $totalPlaces = $reservation->getNbAdults() + $reservation->getNbChildren() + $reservation->getNbInvitations();

            if ($totalPlaces === 0) {
                $this->addFlash('error', 'Veuillez saisir au moins une place.');
            } else {
                $reservation->setStatus('validated');
                $user = $this->getUser();
                $reservation->setCreatedBy($user instanceof User ? $user : null);
                $reservation->setToken(bin2hex(random_bytes(32)));
                $reservation->setCreatedAt(new \DateTimeImmutable());

                $em->persist($reservation);
                $em->flush();

                $audit->log(
                    AuditLogger::RESERVATION_CREATE,
                    sprintf('Création manuelle de la réservation #%d (%s %s)', $reservation->getId(), $reservation->getSpectatorFirstName(), $reservation->getSpectatorLastName()),
                    'Reservation',
                    $reservation->getId(),
                );

                $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' créée.');

                return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
            }
        }

        return $this->render('admin/reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_reservation_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        $previousStatus = $reservation->getStatus();
        $form = $this->createForm(AdminReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUpdatedAt(new \DateTimeImmutable());

            // Si le statut passe à cancelled, libérer les sièges via le service
            if ($previousStatus !== 'cancelled' && $reservation->getStatus() === 'cancelled') {
                $reservationService->cancel($reservation);
                $audit->log(
                    AuditLogger::RESERVATION_CANCEL,
                    sprintf('Annulation de la réservation #%d', $reservation->getId()),
                    'Reservation',
                    $reservation->getId(),
                );
            } else {
                $reservationService->save();
                $audit->log(
                    AuditLogger::RESERVATION_UPDATE,
                    sprintf('Mise à jour de la réservation #%d', $reservation->getId()),
                    'Reservation',
                    $reservation->getId(),
                );
            }

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
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('resend_email_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $mailer->sendConfirmation($reservation);
            $audit->log(
                AuditLogger::RESERVATION_RESEND_EMAIL,
                sprintf('Renvoi email réservation #%d à %s', $reservation->getId(), $reservation->getSpectatorEmail()),
                'Reservation',
                $reservation->getId(),
            );
            $this->addFlash('success', 'Email de confirmation renvoyé à ' . $reservation->getSpectatorEmail() . '.');
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_reservation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('cancel_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $reservationService->cancel($reservation);
            $audit->log(
                AuditLogger::RESERVATION_CANCEL,
                sprintf('Annulation de la réservation #%d', $reservation->getId()),
                'Reservation',
                $reservation->getId(),
            );
            $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' annulée.');
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
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('refund_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $refunded = $helloAssoHandler->refund($reservation);

            if ($refunded) {
                $reservationService->cancel($reservation);
                $mailer->sendCancellation($reservation);
                $audit->log(
                    AuditLogger::RESERVATION_REFUND,
                    sprintf('Remboursement HelloAsso + annulation réservation #%d', $reservation->getId()),
                    'Reservation',
                    $reservation->getId(),
                );
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
