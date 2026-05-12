<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Entity\TicketScan;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\TicketScanRepository;
use App\Service\Pdf\TicketQrCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reçoit les scans de QR codes sur les billets et marque le billet comme contrôlé.
 *
 * Pas de CSRF (le QR est appelé en GET via l'appareil photo du téléphone), mais le HMAC
 * dans l'URL empêche la forge : impossible de générer une URL valide sans la clé secrète.
 */
#[Route('/admin/scan')]
#[IsGranted('ROLE_BILLETTISTE')]
class TicketScanController extends AbstractController
{
    public function __construct(
        private TicketQrCodeGenerator $qrGenerator,
        private ReservationRepository $reservationRepository,
        private TicketScanRepository $ticketScanRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/{tag}/{hmac}', name: 'app_admin_ticket_scan', requirements: ['tag' => '\d+-\d+', 'hmac' => '[a-f0-9]+'])]
    public function scan(string $tag, string $hmac): Response
    {
        if (!$this->qrGenerator->isValidHmac($tag, $hmac)) {
            return $this->renderResult('invalid', 'Code de billet invalide.', null, null);
        }

        [$reservationId, $ticketIndex] = array_map('intval', explode('-', $tag));
        $reservation = $this->reservationRepository->find($reservationId);

        if (!$reservation) {
            return $this->renderResult('invalid', 'Réservation introuvable.', null, null);
        }

        if ($reservation->getStatus() === 'cancelled') {
            return $this->renderResult('cancelled', 'Cette réservation a été annulée.', $reservation, $ticketIndex);
        }

        $existing = $this->ticketScanRepository->findOneByReservationAndIndex($reservation, $ticketIndex);
        if ($existing) {
            return $this->renderResult('already', 'Billet déjà contrôlé le ' . $existing->getScannedAt()->format('d/m/Y à H:i') . '.', $reservation, $ticketIndex, $existing);
        }

        $scan = (new TicketScan())
            ->setReservation($reservation)
            ->setTicketIndex($ticketIndex)
            ->setScannedAt(new \DateTimeImmutable());

        $user = $this->getUser();
        if ($user instanceof User) {
            $scan->setScannedBy($user);
        }

        $this->em->persist($scan);
        $this->em->flush();

        return $this->renderResult('valid', 'Billet valide. Entrée autorisée.', $reservation, $ticketIndex, $scan);
    }

    private function renderResult(string $state, string $message, ?Reservation $reservation, ?int $ticketIndex, ?TicketScan $scan = null): Response
    {
        return $this->render('admin/scan/result.html.twig', [
            'state' => $state,
            'message' => $message,
            'reservation' => $reservation,
            'ticketIndex' => $ticketIndex,
            'scan' => $scan,
        ]);
    }
}
