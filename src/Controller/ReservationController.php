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

        $grouped = [];
        foreach ($representations as $rep) {
            $showId = $rep->getShow()->getId();
            if (!isset($grouped[$showId])) {
                $grouped[$showId] = [
                    'show' => $rep->getShow(),
                    'representations' => [],
                ];
            }
            $booked = $bookedMap[$rep->getId()] ?? 0;
            $grouped[$showId]['representations'][] = [
                'entity' => $rep,
                'booked' => $booked,
                'remaining' => $rep->getMaxOnlineReservations() - $booked,
                'isFull' => $booked >= $rep->getMaxOnlineReservations(),
            ];
        }

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
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
    ): Response {
        $representation = $representationRepository->find($id);

        if (!$representation || $representation->getStatus() !== 'active') {
            throw $this->createNotFoundException('Représentation non disponible.');
        }

        // Vérifier la jauge
        $bookedMap = $representationRepository->findBookedPlacesMap();
        $booked = $bookedMap[$representation->getId()] ?? 0;
        $remaining = $representation->getMaxOnlineReservations() - $booked;

        if ($remaining <= 0) {
            $this->addFlash('error', 'Cette représentation est complète.');

            return $this->redirectToRoute('app_show_detail', ['id' => $representation->getShow()->getId()]);
        }

        // Reprendre une réservation pending existante si le spectateur revient modifier
        $existingPendingId = $request->query->get('edit');
        $reservation = null;
        if ($existingPendingId) {
            $reservation = $reservationRepository->findOneBy([
                'id' => $existingPendingId,
                'representation' => $representation,
                'status' => 'pending',
            ]);
        }

        if (!$reservation) {
            $reservation = new Reservation();
        }

        $isNew = $reservation->getId() === null;
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

            if ($isNew) {
                $reservationService->create($reservation, $representation);
            } else {
                $reservation->setUpdatedAt(new \DateTimeImmutable());
                $reservationService->save();
            }

            return $this->redirectToRoute('app_reservation_summary', [
                'id' => $reservation->getId(),
                'token' => $reservation->getToken(),
            ]);
        }

        return $this->render('public/reservation/new.html.twig', [
            'representation' => $representation,
            'form' => $form,
        ]);
    }

    #[Route('/recapitulatif/{id}/{token}', name: 'app_reservation_summary', requirements: ['id' => '\d+'])]
    public function summary(
        int $id,
        string $token,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        $total = $reservationService->computeTotal($reservation);

        return $this->render('public/reservation/summary.html.twig', [
            'reservation' => $reservation,
            'total' => $total,
        ]);
    }

    #[Route('/payer/{id}/{token}', name: 'app_reservation_pay', requirements: ['id' => '\d+'])]
    public function pay(
        int $id,
        string $token,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
        HelloAssoPaymentHandler $helloAssoHandler,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        if ($reservation->getStatus() !== 'pending') {
            return $this->redirectToRoute('app_reservation_confirmation', [
                'id' => $id,
                'token' => $token,
            ]);
        }

        $total = $reservationService->computeTotal($reservation);
        $checkoutData = $helloAssoHandler->createCheckoutIntent($reservation, $total);

        $reservation->setCheckoutIntentId($checkoutData['id']);
        $reservationService->save();

        return $this->redirect($checkoutData['redirectUrl']);
    }

    #[Route('/retour/{id}/{token}', name: 'app_reservation_return', requirements: ['id' => '\d+'])]
    public function return_(
        int $id,
        string $token,
        ReservationRepository $reservationRepository,
        HelloAssoPaymentHandler $helloAssoHandler,
        ReservationService $reservationService,
        ReservationMailer $reservationMailer,
        LoggerInterface $logger,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        if ($reservation->getStatus() === 'pending' && $reservation->getCheckoutIntentId()) {
            $paid = $helloAssoHandler->handleReturn($reservation, $reservation->getCheckoutIntentId());

            if ($paid) {
                $reservationService->confirm($reservation);
                $reservationMailer->sendConfirmation($reservation);
                $logger->info('Réservation #{id} confirmée via HelloAsso.', ['id' => $reservation->getId()]);
            } else {
                return $this->redirectToRoute('app_reservation_cancel', [
                    'id' => $id,
                    'token' => $token,
                ]);
            }
        }

        return $this->redirectToRoute('app_reservation_confirmation', [
            'id' => $id,
            'token' => $token,
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

    #[Route('/annulation/{id}/{token}', name: 'app_reservation_cancel', requirements: ['id' => '\d+'])]
    public function cancel(
        int $id,
        string $token,
        ReservationRepository $reservationRepository,
        ReservationService $reservationService,
    ): Response {
        $reservation = $this->getReservationByToken($id, $token, $reservationRepository);

        if ($reservation->getStatus() === 'pending') {
            $reservationService->cancel($reservation);
        }

        return $this->render('public/reservation/cancel.html.twig', [
            'reservation' => $reservation,
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
