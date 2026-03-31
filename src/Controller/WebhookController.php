<?php

namespace App\Controller;

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
        LoggerInterface $logger,
    ): Response {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            $reservation = $helloAssoHandler->handleNotification($data);

            if ($reservation) {
                $reservationService->confirm($reservation);
                $reservationMailer->sendConfirmation($reservation);
                $logger->info('Réservation #{id} confirmée via HelloAsso.', ['id' => $reservation->getId()]);
            }
        } catch (\Exception $e) {
            $logger->error('Webhook HelloAsso échoué : {message}', ['message' => $e->getMessage()]);

            return new Response('Webhook error', Response::HTTP_BAD_REQUEST);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
