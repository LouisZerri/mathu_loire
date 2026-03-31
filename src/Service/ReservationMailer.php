<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class ReservationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TicketPdfGenerator $ticketPdfGenerator,
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

        $pdf = $this->ticketPdfGenerator->generate($reservation);

        $email = (new Email())
            ->from('l.zerri@gmail.com')
            ->to($reservation->getSpectatorEmail())
            ->subject('Confirmation de réservation - ' . $representation->getShow()->getTitle())
            ->html($html)
            ->attach($pdf, sprintf('billet-%d.pdf', $reservation->getId()), 'application/pdf');

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
            ->from('l.zerri@gmail.com')
            ->to($reservation->getSpectatorEmail())
            ->subject('Annulation de réservation - ' . $representation->getShow()->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }
}
