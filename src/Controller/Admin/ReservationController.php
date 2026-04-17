<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\AdminReservationType;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\Security\AuditLogger;
use App\Service\Reservation\ReservationCsvExporter;
use App\Service\Reservation\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère les actions CRUD sur les réservations côté administration.
 */
#[Route('/admin/reservations')]
#[IsGranted('ROLE_BILLETTISTE')]
class ReservationController extends AbstractController
{
    /**
     * Liste les réservations avec filtres par représentation, statut, année et recherche.
     *
     * @return Response
     */
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
        $defaultYear = !empty($availableYears) && in_array($currentYear, $availableYears) ? $currentYear : ($availableYears[0] ?? $currentYear);
        $selectedYear = (int) $request->query->get('year', $defaultYear);

        $representation = $repId ? $representationRepository->find($repId) : null;
        $statusFilter = $status ?: null;
        $searchFilter = $search !== '' ? $search : null;
        // En mode recherche, on cherche sur toutes les saisons (pas de filtre année)
        $yearFilter = $searchFilter ? null : $selectedYear;

        $reservations = $reservationRepository->findByFilters($representation, $statusFilter, $page, 20, $yearFilter, $searchFilter);
        $totalReservations = $reservationRepository->countByFilters($representation, $statusFilter, $yearFilter, $searchFilter);

        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $reservations,
            'representations' => $representationRepository->findByYear($selectedYear),
            'currentRep' => $representation,
            'currentStatus' => $statusFilter,
            'currentSearch' => $searchFilter,
            'currentPage' => $page,
            'totalPages' => max(1, (int) ceil($totalReservations / 20)),
            'totalResults' => $totalReservations,
            'cancelledReservations' => $searchFilter ? [] : $reservationRepository->findByFilters(null, 'cancelled', 1, 50, $selectedYear),
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    /**
     * Exporte les réservations filtrées au format CSV compatible Excel.
     *
     * @return Response
     */
    #[Route('/export', name: 'app_admin_reservation_export')]
    public function export(
        Request $request,
        ReservationRepository $reservationRepository,
        RepresentationRepository $representationRepository,
        ReservationCsvExporter $csvExporter,
    ): Response {
        $repId = (int) $request->query->get('representation', 0);
        $status = $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));
        $year = (int) $request->query->get('year', 0) ?: null;

        $representation = $repId ? $representationRepository->find($repId) : null;
        $searchFilter = $search !== '' ? $search : null;
        $yearFilter = $searchFilter ? null : $year;

        $reservations = $reservationRepository->findByFilters($representation, $status ?: null, 1, 10000, $yearFilter, $searchFilter);

        $csv = $csvExporter->export($reservations);

        // BOM UTF-8 : nécessaire pour qu'Excel interprète correctement les accents
        return new Response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservations-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Crée manuellement une nouvelle réservation depuis l'interface d'administration.
     *
     * @return Response
     */
    #[Route('/new', name: 'app_admin_reservation_new')]
    public function new(Request $request, ReservationService $reservationService, AuditLogger $audit): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(AdminReservationType::class, $reservation, ['show_payment_method' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $totalPlaces = $reservation->getNbAdults() + $reservation->getNbChildren() + $reservation->getNbInvitations();

            if ($totalPlaces === 0) {
                $this->addFlash('error', 'Veuillez saisir au moins une place.');
            } else {
                $user = $this->getUser();
                $paymentMethod = $form->has('paymentMethod') ? $form->get('paymentMethod')->getData() : null;
                $reservationService->createManual($reservation, $user instanceof User ? $user : null, $paymentMethod);

                $audit->log(AuditLogger::RESERVATION_CREATE, sprintf('Création manuelle de la réservation #%d (%s %s)', $reservation->getId(), $reservation->getSpectatorFirstName(), $reservation->getSpectatorLastName()), 'Reservation', $reservation->getId());
                $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' créée.');

                return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
            }
        }

        return $this->render('admin/reservation/new.html.twig', ['reservation' => $reservation, 'form' => $form]);
    }

    /**
     * Modifie une réservation existante avec gestion du changement de statut vers annulé.
     *
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_reservation_edit', requirements: ['id' => '\d+'])]
    public function edit(Reservation $reservation, Request $request, ReservationService $reservationService, AuditLogger $audit): Response
    {
        $previousStatus = $reservation->getStatus();
        $form = $this->createForm(AdminReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUpdatedAt(new \DateTimeImmutable());

            if ($previousStatus !== 'cancelled' && $reservation->getStatus() === 'cancelled') {
                $reservationService->cancel($reservation);
                $audit->log(AuditLogger::RESERVATION_CANCEL, sprintf('Annulation de la réservation #%d', $reservation->getId()), 'Reservation', $reservation->getId());
            } else {
                $reservationService->save();
                $audit->log(AuditLogger::RESERVATION_UPDATE, sprintf('Mise à jour de la réservation #%d', $reservation->getId()), 'Reservation', $reservation->getId());
            }

            $this->addFlash('success', 'Réservation #' . $reservation->getId() . ' mise à jour.');

            return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
        }

        return $this->render('admin/reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'total' => $reservationService->computeTotal($reservation),
        ]);
    }

}
