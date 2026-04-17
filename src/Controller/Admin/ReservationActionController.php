<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Service\Security\AuditLogger;
use App\Service\HelloAsso\HelloAssoPaymentHandler;
use App\Service\Reservation\ReservationMailer;
use App\Service\Reservation\ReservationService;
use App\Service\Pdf\TicketThermalPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère les actions ponctuelles sur une réservation : renvoi d'email, annulation, remboursement et impression.
 */
#[Route('/admin/reservations')]
#[IsGranted('ROLE_BILLETTISTE')]
class ReservationActionController extends AbstractController
{
    /**
     * Renvoie l'email de confirmation au spectateur pour une réservation donnée.
     *
     * @return Response
     */
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

    /**
     * Annule une réservation et libère les places associées.
     *
     * @return Response
     */
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

    /**
     * Effectue le remboursement HelloAsso puis annule la réservation et notifie le spectateur.
     *
     * @return Response
     */
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

    /**
     * Enregistre un paiement manuel (au guichet) pour une réservation non payée.
     *
     * @return Response
     */
    #[Route('/{id}/mark-paid', name: 'app_admin_reservation_mark_paid', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markPaid(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('mark_paid_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $reservationService->markAsPaid($reservation);
            $audit->log(
                AuditLogger::RESERVATION_UPDATE,
                sprintf('Paiement au guichet enregistré pour la réservation #%d', $reservation->getId()),
                'Reservation',
                $reservation->getId(),
            );
            $this->addFlash('success', 'Paiement enregistré pour la réservation #' . $reservation->getId() . '.');
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    /**
     * Génère et affiche le PDF des billets thermiques pour une réservation.
     *
     * @return Response
     */
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
