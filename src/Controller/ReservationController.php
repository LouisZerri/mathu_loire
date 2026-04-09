<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\HelloAssoPaymentHandler;
use App\Service\ReservationMailer;
use App\Service\ReservationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/billetterie')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_reservation_index')]
    public function index(Request $request, RepresentationRepository $representationRepository): Response
    {
        $representations = $representationRepository->findUpcoming();
        $bookedMap = $representationRepository->findBookedPlacesMap();

        $now = new \DateTime();
        $grouped = [];
        foreach ($representations as $rep) {
            $showId = $rep->getShow()->getId();
            if (!isset($grouped[$showId])) {
                $grouped[$showId] = [
                    'show' => $rep->getShow(),
                    'representations' => [],
                    'totalBooked' => 0,
                    'totalCapacity' => 0,
                    'allFull' => true,
                    'nextRep' => null,
                ];
            }
            $booked = $bookedMap[$rep->getId()] ?? 0;
            $max = $rep->getMaxOnlineReservations();
            $remaining = $max - $booked;
            $isFull = $booked >= $max;
            $daysUntil = (int) $now->diff($rep->getDatetime())->format('%r%a');

            $repData = [
                'entity' => $rep,
                'booked' => $booked,
                'remaining' => $remaining,
                'isFull' => $isFull,
                'isAlmostFull' => !$isFull && $remaining <= 10,
                'daysUntil' => $daysUntil,
            ];
            $grouped[$showId]['representations'][] = $repData;
            $grouped[$showId]['totalBooked'] += $booked;
            $grouped[$showId]['totalCapacity'] += $max;
            if (!$isFull) {
                $grouped[$showId]['allFull'] = false;
            }
            if ($grouped[$showId]['nextRep'] === null) {
                $grouped[$showId]['nextRep'] = $repData;
            }
        }

        // Tri : spectacles avec prochaine date non-complète d'abord, puis par date croissante
        uasort($grouped, function ($a, $b) {
            if ($a['allFull'] !== $b['allFull']) {
                return $a['allFull'] <=> $b['allFull'];
            }
            return $a['nextRep']['entity']->getDatetime() <=> $b['nextRep']['entity']->getDatetime();
        });

        $perPage = 12;
        $page = max(1, (int) $request->query->get('page', 1));
        $total = count($grouped);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $grouped = array_slice($grouped, ($page - 1) * $perPage, $perPage);

        return $this->render('public/reservation/index.html.twig', [
            'grouped' => $grouped,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_new', requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
    ): Response {
        $representation = $representationRepository->find($id);

        if (!$representation || $representation->getStatus() !== 'active') {
            throw $this->createNotFoundException('Représentation non disponible.');
        }

        $bookedMap = $representationRepository->findBookedPlacesMap();
        $booked = $bookedMap[$representation->getId()] ?? 0;
        $remaining = $representation->getMaxOnlineReservations() - $booked;

        if ($remaining <= 0) {
            $this->addFlash('error', 'Cette représentation est complète.');

            return $this->redirectToRoute('app_show_detail', ['id' => $representation->getShow()->getId()]);
        }

        $reservation = new Reservation();

        // Pré-remplir depuis la session si on revient du récap
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');
        if ($draft && ($draft['representation_id'] ?? 0) === $id) {
            $reservation->setNbAdults($draft['nbAdults'] ?? 0);
            $reservation->setNbChildren($draft['nbChildren'] ?? 0);
            $reservation->setIsPMR($draft['isPMR'] ?? false);
            $reservation->setSpectatorLastName($draft['lastName'] ?? '');
            $reservation->setSpectatorFirstName($draft['firstName'] ?? '');
            $reservation->setSpectatorCity($draft['city'] ?? '');
            $reservation->setSpectatorPhone($draft['phone'] ?? '');
            $reservation->setSpectatorEmail($draft['email'] ?? '');
            $reservation->setSpectatorComment($draft['comment'] ?? null);
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

            // Stocker en session au lieu de persister en BDD
            $session->set('reservation_draft', [
                'representation_id' => $id,
                'nbAdults' => $reservation->getNbAdults(),
                'nbChildren' => $reservation->getNbChildren(),
                'isPMR' => $reservation->isPMR(),
                'lastName' => $reservation->getSpectatorLastName(),
                'firstName' => $reservation->getSpectatorFirstName(),
                'city' => $reservation->getSpectatorCity(),
                'phone' => $reservation->getSpectatorPhone(),
                'email' => $reservation->getSpectatorEmail(),
                'comment' => $reservation->getSpectatorComment(),
            ]);

            return $this->redirectToRoute('app_reservation_summary', ['id' => $id]);
        }

        return $this->render('public/reservation/new.html.twig', [
            'representation' => $representation,
            'form' => $form,
        ]);
    }

    #[Route('/recapitulatif/{id}', name: 'app_reservation_summary', requirements: ['id' => '\d+'])]
    public function summary(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
    ): Response {
        $draft = $request->getSession()->get('reservation_draft');

        if (!$draft || ($draft['representation_id'] ?? 0) !== $id) {
            return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
        }

        $representation = $representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        $total = ($draft['nbAdults'] * (float) $representation->getAdultPrice())
               + ($draft['nbChildren'] * (float) $representation->getChildPrice());

        return $this->render('public/reservation/summary.html.twig', [
            'draft' => $draft,
            'representation' => $representation,
            'total' => $total,
        ]);
    }

    #[Route('/payer/{id}', name: 'app_reservation_pay', requirements: ['id' => '\d+'])]
    public function pay(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
        HelloAssoPaymentHandler $helloAssoHandler,
    ): Response {
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');

        if (!$draft || ($draft['representation_id'] ?? 0) !== $id) {
            return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
        }

        $representation = $representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        $total = ($draft['nbAdults'] * (float) $representation->getAdultPrice())
               + ($draft['nbChildren'] * (float) $representation->getChildPrice());

        // Générer un token unique pour identifier ce draft
        $draftToken = bin2hex(random_bytes(32));
        $draft['token'] = $draftToken;
        $session->set('reservation_draft', $draft);

        $checkoutData = $helloAssoHandler->createCheckoutIntentFromDraft($draft, $representation, $total, $draftToken);

        $session->set('reservation_checkout_id', $checkoutData['id']);

        return $this->redirect($checkoutData['redirectUrl']);
    }

    #[Route('/retour/{id}', name: 'app_reservation_return', requirements: ['id' => '\d+'])]
    public function return_(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
        ReservationRepository $reservationRepository,
        HelloAssoPaymentHandler $helloAssoHandler,
        ReservationService $reservationService,
        ReservationMailer $reservationMailer,
        LoggerInterface $logger,
    ): Response {
        $session = $request->getSession();
        $draft = $session->get('reservation_draft');
        $checkoutId = $session->get('reservation_checkout_id');

        if (!$draft || !$checkoutId || ($draft['representation_id'] ?? 0) !== $id) {
            $this->addFlash('error', 'Session expirée. Veuillez recommencer.');

            return $this->redirectToRoute('app_reservation_index');
        }

        $representation = $representationRepository->find($id);
        if (!$representation) {
            throw $this->createNotFoundException();
        }

        $paymentData = $helloAssoHandler->verifyCheckout($checkoutId);

        if ($paymentData) {
            // Vérifier qu'une résa n'existe pas déjà pour ce checkout (webhook arrivé avant)
            $existing = $reservationRepository->findOneBy(['checkoutIntentId' => $checkoutId]);
            if ($existing) {
                $session->remove('reservation_draft');
                $session->remove('reservation_checkout_id');

                return $this->redirectToRoute('app_reservation_confirmation', [
                    'id' => $existing->getId(),
                    'token' => $existing->getToken(),
                ]);
            }

            // Créer la résa en BDD maintenant que le paiement est confirmé
            $reservation = $reservationService->createFromDraft($draft, $representation);
            $reservation->setCheckoutIntentId($checkoutId);
            $helloAssoHandler->recordPayment($reservation, $paymentData);
            $reservationService->confirm($reservation);
            $reservationMailer->sendConfirmation($reservation);
            $logger->info('Réservation #{id} créée et confirmée après paiement HelloAsso.', ['id' => $reservation->getId()]);

            // Nettoyer la session
            $session->remove('reservation_draft');
            $session->remove('reservation_checkout_id');

            return $this->redirectToRoute('app_reservation_confirmation', [
                'id' => $reservation->getId(),
                'token' => $reservation->getToken(),
            ]);
        }

        // Paiement échoué
        $session->remove('reservation_checkout_id');

        return $this->render('public/reservation/cancel.html.twig', [
            'representation' => $representation,
        ]);
    }

    #[Route('/confirmation/{id}/{token}', name: 'app_reservation_confirmation', requirements: ['id' => '\d+'])]
    public function confirmation(int $id, string $token, ReservationRepository $reservationRepository): Response
    {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        return $this->render('public/reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/annulation-paiement/{id}', name: 'app_reservation_cancel', requirements: ['id' => '\d+'])]
    public function cancel(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
    ): Response {
        $representation = $representationRepository->find($id);

        // Nettoyer la session
        $session = $request->getSession();
        $session->remove('reservation_draft');
        $session->remove('reservation_checkout_id');

        return $this->render('public/reservation/cancel.html.twig', [
            'representation' => $representation,
        ]);
    }

    #[Route('/suivi/{id}/{token}/annuler', name: 'app_reservation_self_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function selfCancel(
        int $id,
        string $token,
        Request $request,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
        ReservationMailer $reservationMailer,
        HelloAssoPaymentHandler $helloAssoHandler,
        LoggerInterface $logger,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        if (!$this->isCsrfTokenValid('self_cancel_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createNotFoundException();
        }

        if ($reservation->getStatus() !== 'validated') {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');

            return $this->redirectToRoute('app_reservation_tracking', ['id' => $id, 'token' => $token]);
        }

        $hoursUntilShow = ($reservation->getRepresentation()->getDatetime()->getTimestamp() - time()) / 3600;
        if ($hoursUntilShow < 48) {
            $this->addFlash('error', 'Annulation impossible moins de 48h avant le spectacle.');

            return $this->redirectToRoute('app_reservation_tracking', ['id' => $id, 'token' => $token]);
        }

        // Remboursement automatique HelloAsso
        if ($reservation->getPayments()->count() > 0) {
            $refunded = $helloAssoHandler->refund($reservation);
            if (!$refunded) {
                $logger->error('Remboursement HelloAsso échoué pour la réservation #{id}', ['id' => $reservation->getId()]);
            }
        }

        $reservationService->cancel($reservation);
        $reservationMailer->sendCancellation($reservation);

        return $this->redirectToRoute('app_reservation_tracking', ['id' => $id, 'token' => $token]);
    }

    #[Route('/suivi/{id}/{token}', name: 'app_reservation_tracking', requirements: ['id' => '\d+'])]
    public function tracking(
        int $id,
        string $token,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);
        $total = $reservationService->computeTotal($reservation);

        return $this->render('public/reservation/tracking.html.twig', [
            'reservation' => $reservation,
            'total' => $total,
        ]);
    }

    private function getReservationByToken(int $id, string $token, ReservationRepository $repository): Reservation
    {
        $reservation = $repository->findOneBy(['id' => $id, 'token' => $token]);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $reservation;
    }
}
