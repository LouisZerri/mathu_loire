<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\Booking\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gère le parcours de réservation en ligne pour les spectateurs.
 */
#[Route('/billetterie')]
class BookingController extends AbstractController
{
    public function __construct(
        private RepresentationRepository $representationRepository,
        private BookingService $bookingService,
    ) {
    }

    /**
     * Affiche la liste paginée des spectacles disponibles à la réservation.
     *
     * @return Response
     */
    #[Route('/', name: 'app_reservation_index')]
    public function index(Request $request): Response
    {
        $grouped = $this->bookingService->getGroupedShows();
        $perPage = 12;
        $page = max(1, (int) $request->query->get('page', 1));
        $totalPages = max(1, (int) ceil(count($grouped) / $perPage));
        $page = min($page, $totalPages);

        return $this->render('public/reservation/index.html.twig', [
            'grouped' => array_slice($grouped, ($page - 1) * $perPage, $perPage),
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Affiche le formulaire de réservation et enregistre le brouillon en session.
     *
     * @return Response
     */
    #[Route('/{id}', name: 'app_reservation_new', requirements: ['id' => '\d+'])]
    public function new(int $id, Request $request): Response
    {
        $representation = $this->representationRepository->find($id);
        if (!$representation || $representation->getStatus() !== 'active') {
            throw $this->createNotFoundException('Représentation non disponible.');
        }

        $remaining = $this->bookingService->getRemainingPlaces($representation);
        if ($remaining <= 0) {
            $this->addFlash('error', 'Cette représentation est complète.');
            return $this->redirectToRoute('app_show_detail', ['id' => $representation->getShow()->getId()]);
        }

        $reservation = new Reservation();
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');
        if ($draft && ($draft['representation_id'] ?? 0) === $id) {
            $this->bookingService->hydrateFromDraft($reservation, $draft);
        }

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $totalPlaces = $reservation->getNbAdults() + $reservation->getNbChildren();
            if ($totalPlaces === 0) {
                $this->addFlash('error', 'Veuillez sélectionner au moins une place.');
                return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
            }
            if ($totalPlaces > $remaining) {
                $this->addFlash('error', 'Il ne reste que ' . $remaining . ' place(s) disponible(s).');
                return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
            }

            $session->set('reservation_draft', $this->bookingService->buildDraft($id, $reservation));
            return $this->redirectToRoute('app_reservation_summary', ['id' => $id]);
        }

        return $this->render('public/reservation/new.html.twig', [
            'representation' => $representation,
            'form' => $form,
        ]);
    }

    /**
     * Affiche le récapitulatif de la réservation avant le paiement.
     *
     * @return Response
     */
    #[Route('/recapitulatif/{id}', name: 'app_reservation_summary', requirements: ['id' => '\d+'])]
    public function summary(int $id, Request $request): Response
    {
        $draft = $request->getSession()->get('reservation_draft');
        if (!$draft || ($draft['representation_id'] ?? 0) !== $id) {
            return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
        }

        $representation = $this->representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        return $this->render('public/reservation/summary.html.twig', [
            'draft' => $draft,
            'representation' => $representation,
            'total' => $this->bookingService->computeTotalFromDraft($draft, $representation),
        ]);
    }

    /**
     * Initie le paiement HelloAsso et redirige vers la page de checkout.
     *
     * @return Response
     */
    #[Route('/payer/{id}', name: 'app_reservation_pay', requirements: ['id' => '\d+'])]
    public function pay(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');
        if (!$draft || ($draft['representation_id'] ?? 0) !== $id) {
            return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
        }

        $representation = $this->representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        $result = $this->bookingService->initiateCheckout($draft, $representation);
        $session->set('reservation_draft', $result['draft']);
        $session->set('reservation_checkout_id', $result['checkoutId']);

        return $this->redirect($result['redirectUrl']);
    }

    /**
     * Traite le retour après paiement HelloAsso et finalise la réservation.
     *
     * @return Response
     */
    #[Route('/retour/{id}', name: 'app_reservation_return', requirements: ['id' => '\d+'])]
    public function return_(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');
        $checkoutId = $session->get('reservation_checkout_id');

        if (!$draft || !$checkoutId || ($draft['representation_id'] ?? 0) !== $id) {
            $this->addFlash('error', 'Session expirée. Veuillez recommencer.');
            return $this->redirectToRoute('app_reservation_index');
        }

        $representation = $this->representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        $reservation = $this->bookingService->processReturn($draft, $checkoutId, $representation);
        if ($reservation) {
            $session->remove('reservation_draft');
            $session->remove('reservation_checkout_id');
            return $this->redirectToRoute('app_reservation_confirmation', [
                'id' => $reservation->getId(),
                'token' => $reservation->getToken(),
            ]);
        }

        $session->remove('reservation_checkout_id');
        return $this->render('public/reservation/cancel.html.twig', ['representation' => $representation]);
    }

    /**
     * Affiche la page de confirmation après une réservation réussie.
     *
     * @return Response
     */
    #[Route('/confirmation/{id}/{token}', name: 'app_reservation_confirmation', requirements: ['id' => '\d+'])]
    public function confirmation(int $id, string $token, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->findOneBy(['id' => $id, 'token' => $token]);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $this->render('public/reservation/confirmation.html.twig', ['reservation' => $reservation]);
    }

    /**
     * Affiche la page d'annulation de paiement et nettoie la session.
     *
     * @return Response
     */
    #[Route('/annulation-paiement/{id}', name: 'app_reservation_cancel', requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): Response
    {
        $representation = $this->representationRepository->find($id);
        $session = $request->getSession();
        $session->remove('reservation_draft');
        $session->remove('reservation_checkout_id');

        return $this->render('public/reservation/cancel.html.twig', ['representation' => $representation]);
    }
}
