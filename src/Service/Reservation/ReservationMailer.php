<?php

namespace App\Service\Reservation;

use App\Entity\Reservation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Envoie les emails transactionnels liés au cycle de vie d'une réservation.
 */
class ReservationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private ReservationService $reservationService,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailerFrom,
    ) {
    }

    /**
     * Envoie l'email de confirmation de réservation au spectateur.
     *
     * @param Reservation $reservation Réservation confirmée
     * @return void
     */
    public function sendConfirmation(Reservation $reservation): void
    {
        $representation = $reservation->getRepresentation();
        $total = $this->reservationService->computeTotal($reservation);

        $html = $this->twig->render('email/reservation_confirmation.html.twig', [
            'reservation' => $reservation,
            'total' => $total,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($reservation->getSpectatorEmail())
            ->subject('Confirmation de réservation - ' . $representation->getShow()->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Envoie le rappel J-2 avant la représentation au spectateur.
     *
     * @param Reservation $reservation Réservation à rappeler
     * @return void
     */
    public function sendReminder(Reservation $reservation): void
    {
        $html = $this->twig->render('email/reservation_reminder.html.twig', [
            'reservation' => $reservation,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($reservation->getSpectatorEmail())
            ->subject('Rappel : ' . $reservation->getRepresentation()->getShow()->getTitle() . ' dans 2 jours !')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Envoie l'email d'annulation de réservation au spectateur.
     *
     * @param Reservation $reservation Réservation annulée
     * @return void
     */
    public function sendCancellation(Reservation $reservation): void
    {
        $representation = $reservation->getRepresentation();
        $total = $this->reservationService->computeTotal($reservation);

        $html = $this->twig->render('email/reservation_cancellation.html.twig', [
            'reservation' => $reservation,
            'total' => $total,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($reservation->getSpectatorEmail())
            ->subject('Annulation de réservation - ' . $representation->getShow()->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }
}
