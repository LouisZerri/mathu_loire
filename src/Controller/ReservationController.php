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
    public function index(RepresentationRepository $representationRepository): Response
    {
        $representations = $representationRepository->findUpcoming();

        return $this->render('public/reservation/index.html.twig', [
            'representations' => $representations,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_new', requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        RepresentationRepository $representationRepository,
        ReservationService $reservationService,
    ): Response {
        $representation = $representationRepository->find($id);

        if (!$representation || $representation->getStatus() !== 'active') {
            throw $this->createNotFoundException('Représentation non disponible.');
        }

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reservation->getNbAdults() + $reservation->getNbChildren() === 0) {
                $this->addFlash('error', 'Veuillez sélectionner au moins une place.');

                return $this->redirectToRoute('app_reservation_new', ['id' => $id]);
            }

            $reservationService->create($reservation, $representation);

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

    private function getReservationByToken(int $id, string $token, ReservationRepository $repository): Reservation
    {
        $reservation = $repository->findOneBy(['id' => $id, 'token' => $token]);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $reservation;
    }
}
