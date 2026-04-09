<?php

namespace App\Controller;

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

class WebhookController extends AbstractController
{
    #[Route('/webhook/helloasso', name: 'app_webhook_helloasso', methods: ['POST'])]
    public function helloasso(
        Request $request,
        HelloAssoPaymentHandler $helloAssoHandler,
        ReservationService $reservationService,
        ReservationMailer $reservationMailer,
        RepresentationRepository $representationRepository,
        ReservationRepository $reservationRepository,
        LoggerInterface $logger,
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentInfo = $helloAssoHandler->handleNotification($data);

            if (!$paymentInfo) {
                return new Response('OK', Response::HTTP_OK);
            }

            // Vérifier si une résa existe déjà pour ce checkout (créée par le return)
            $checkoutIntentId = $data['data']['meta']['checkoutIntentId'] ?? $paymentInfo['transaction_id'];
            $existing = $reservationRepository->findOneBy(['checkoutIntentId' => $checkoutIntentId]);
            if ($existing) {
                $logger->info('Webhook: réservation déjà traitée pour checkout {id}', ['id' => $checkoutIntentId]);

                return new Response('OK', Response::HTTP_OK);
            }

            // Créer la résa depuis les données webhook (filet de sécurité si le return a raté)
            $representation = $representationRepository->find($paymentInfo['representation_id']);
            if (!$representation) {
                $logger->warning('Webhook: représentation {id} introuvable', ['id' => $paymentInfo['representation_id']]);

                return new Response('OK', Response::HTTP_OK);
            }

            $payer = $paymentInfo['payer'] ?? [];
            $draft = [
                'nbAdults' => 1,
                'nbChildren' => 0,
                'isPMR' => false,
                'lastName' => $payer['lastName'] ?? 'Inconnu',
                'firstName' => $payer['firstName'] ?? 'Inconnu',
                'city' => '',
                'phone' => '',
                'email' => $payer['email'] ?? '',
                'comment' => 'Créée automatiquement via webhook HelloAsso',
            ];

            $reservation = $reservationService->createFromDraft($draft, $representation);
            $reservation->setCheckoutIntentId((int) $checkoutIntentId);
            $helloAssoHandler->recordPayment($reservation, [
                'amount' => $paymentInfo['amount'] * 100,
                'id' => $paymentInfo['transaction_id'],
            ]);
            $reservationService->confirm($reservation);
            $reservationMailer->sendConfirmation($reservation);
            $logger->info('Réservation #{id} créée via webhook HelloAsso.', ['id' => $reservation->getId()]);
        } catch (\Exception $e) {
            $logger->error('Webhook HelloAsso échoué : {message}', ['message' => $e->getMessage()]);

            return new Response('Webhook error', Response::HTTP_BAD_REQUEST);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
