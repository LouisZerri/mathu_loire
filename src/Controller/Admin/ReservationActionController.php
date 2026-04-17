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
     * Ajoute un paiement partiel ou total à une réservation.
     *
     * @return Response
     */
    #[Route('/{id}/add-payment', name: 'app_admin_reservation_add_payment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addPayment(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('add_payment_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $method = (string) $request->request->get('method', '');
            $amount = (float) $request->request->get('amount', 0);

            if ($method && $amount > 0) {
                $reservationService->addPayment($reservation, $method, $amount);
                $audit->log(AuditLogger::RESERVATION_UPDATE, sprintf('Paiement ajouté : %.2f€ en %s (résa #%d)', $amount, $method, $reservation->getId()), 'Reservation', $reservation->getId());
                $this->addFlash('success', sprintf('Paiement de %.2f € (%s) enregistré.', $amount, $method));
            } else {
                $this->addFlash('error', 'Montant et mode de paiement requis.');
            }
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    /**
     * Modifie le mode de paiement et/ou le montant d'un enregistrement existant.
     *
     * @return Response
     */
    #[Route('/{id}/edit-payment/{paymentId}', name: 'app_admin_reservation_edit_payment', requirements: ['id' => '\d+', 'paymentId' => '\d+'], methods: ['POST'])]
    public function editPayment(
        Reservation $reservation,
        int $paymentId,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('edit_payment_' . $paymentId, (string) $request->request->get('_token'))) {
            $payment = null;
            foreach ($reservation->getPayments() as $p) {
                if ($p->getId() === $paymentId) { $payment = $p; break; }
            }

            if ($payment && $payment->getType() === 'payment') {
                $method = (string) $request->request->get('method', $payment->getMethod());
                $amount = (float) $request->request->get('amount', (float) $payment->getAmount());
                $reservationService->editPayment($payment, $method, $amount);
                $audit->log(AuditLogger::RESERVATION_UPDATE, sprintf('Paiement #%d modifié : %.2f€ en %s (résa #%d)', $paymentId, $amount, $method, $reservation->getId()), 'Reservation', $reservation->getId());
                $this->addFlash('success', 'Paiement modifié.');
            }
        }

        return $this->redirectToRoute('app_admin_reservation_edit', ['id' => $reservation->getId()]);
    }

    /**
     * Supprime un enregistrement de paiement.
     *
     * @return Response
     */
    #[Route('/{id}/delete-payment/{paymentId}', name: 'app_admin_reservation_delete_payment', requirements: ['id' => '\d+', 'paymentId' => '\d+'], methods: ['POST'])]
    public function deletePayment(
        Reservation $reservation,
        int $paymentId,
        Request $request,
        ReservationService $reservationService,
        AuditLogger $audit,
    ): Response {
        if ($this->isCsrfTokenValid('delete_payment_' . $paymentId, (string) $request->request->get('_token'))) {
            $payment = null;
            foreach ($reservation->getPayments() as $p) {
                if ($p->getId() === $paymentId && $p->getType() === 'payment') { $payment = $p; break; }
            }

            if ($payment) {
                $reservationService->deletePayment($payment);
                $audit->log(AuditLogger::RESERVATION_UPDATE, sprintf('Paiement #%d supprimé (résa #%d)', $paymentId, $reservation->getId()), 'Reservation', $reservation->getId());
                $this->addFlash('success', 'Paiement supprimé.');
            }
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
