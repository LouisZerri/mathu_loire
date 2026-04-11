<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\HelloAsso\HelloAssoPaymentHandler;
use App\Service\Reservation\ReservationMailer;
use App\Service\Reservation\ReservationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Permet au spectateur de consulter et d'annuler sa réservation via un lien sécurisé.
 */
#[Route('/billetterie')]
class TrackingController extends AbstractController
{
    /**
     * Affiche le suivi d'une réservation identifiée par son jeton de sécurité.
     *
     * @param int $id L'identifiant de la réservation
     * @param string $token Le jeton de sécurité de la réservation
     *
     * @return Response
     */
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

    /**
     * Permet au spectateur d'annuler lui-même sa réservation avec remboursement automatique si applicable.
     *
     * @param int $id L'identifiant de la réservation
     * @param string $token Le jeton de sécurité de la réservation
     *
     * @return Response
     */
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

        if (!$this->isCsrfTokenValid('self_cancel_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createNotFoundException();
        }

        if ($reservation->getStatus() !== 'validated') {
            $this->addFlash('error', 'Cette réservation ne peut pas être annulée.');

            return $this->redirectToRoute('app_reservation_tracking', ['id' => $id, 'token' => $token]);
        }

        // Règle métier : auto-annulation interdite à moins de 48h du spectacle
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

    /**
     * Récupère une réservation par son identifiant et son jeton de sécurité, ou lève une 404.
     *
     * @param int $id L'identifiant de la réservation
     * @param string $token Le jeton de sécurité
     * @param ReservationRepository $repository Le dépôt des réservations
     *
     * @return Reservation
     */
    private function getReservationByToken(int $id, string $token, ReservationRepository $repository): Reservation
    {
        $reservation = $repository->findOneBy(['id' => $id, 'token' => $token]);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $reservation;
    }
}
