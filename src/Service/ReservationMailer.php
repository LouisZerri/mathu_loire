<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class ReservationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailerFrom,
    ) {
    }

    public function sendConfirmation(Reservation $reservation): void
    {
        $representation = $reservation->getRepresentation();
        $total = ($reservation->getNbAdults() * (float) $representation->getAdultPrice())
               + ($reservation->getNbChildren() * (float) $representation->getChildPrice());

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

    public function sendCancellation(Reservation $reservation): void
    {
        $representation = $reservation->getRepresentation();
        $total = ($reservation->getNbAdults() * (float) $representation->getAdultPrice())
               + ($reservation->getNbChildren() * (float) $representation->getChildPrice());

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
