<?php

namespace App\Service\Booking;

use App\Entity\Representation;
use App\Entity\Reservation;
use App\Repository\RepresentationRepository;
use App\Repository\ReservationRepository;
use App\Service\HelloAsso\HelloAssoPaymentHandler;
use App\Service\Reservation\ReservationMailer;
use App\Service\Reservation\ReservationService;
use Psr\Log\LoggerInterface;

/**
 * Gère le flow de réservation côté public :
 * billetterie → formulaire → récap → paiement HelloAsso → confirmation.
 *
 * Le "draft" est un array stocké en session contenant les données du formulaire
 * AVANT création en BDD (la résa n'est persistée qu'après paiement confirmé).
 *
 * @phpstan-type Draft array{representation_id: int, nbAdults: int, nbChildren: int, isPMR: bool, lastName: string, firstName: string, city: string, phone: string, email: string, comment: string|null, token?: string}
 */
class BookingService
{
    public function __construct(
        private RepresentationRepository $representationRepository,
        private ReservationRepository $reservationRepository,
        private ReservationService $reservationService,
        private ReservationMailer $reservationMailer,
        private HelloAssoPaymentHandler $helloAssoHandler,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Récupère les spectacles à venir regroupés par show avec statistiques de remplissage.
     *
     * @return array<int|string, array>
     */
    public function getGroupedShows(): array
    {
        $representations = $this->representationRepository->findUpcoming();
        $bookedMap = $this->representationRepository->findBookedPlacesMap();

        $now = new \DateTime();
        $grouped = [];
        foreach ($representations as $rep) {
            $showId = $rep->getShow()->getId();
            if (!isset($grouped[$showId])) {
                $grouped[$showId] = [
                    'show' => $rep->getShow(),
                    'representations' => [],
                    'totalBooked' => 0,
                    'totalCapacity' => 0,
                    'allFull' => true,
                    'nextRep' => null,
                ];
            }
            $booked = $bookedMap[$rep->getId()] ?? 0;
            $max = $rep->getMaxOnlineReservations();
            $remaining = $max - $booked;
            $isFull = $booked >= $max;

            // %r%a = jours signés : positif = futur, négatif = passé (utilisé pour le badge "J-X" sur la billetterie)
            $repData = [
                'entity' => $rep,
                'booked' => $booked,
                'remaining' => $remaining,
                'isFull' => $isFull,
                'isAlmostFull' => !$isFull && $remaining <= 10,
                'daysUntil' => (int) $now->diff($rep->getDatetime())->format('%r%a'),
            ];
            $grouped[$showId]['representations'][] = $repData;
            $grouped[$showId]['totalBooked'] += $booked;
            $grouped[$showId]['totalCapacity'] += $max;
            if (!$isFull) {
                $grouped[$showId]['allFull'] = false;
            }
            if ($grouped[$showId]['nextRep'] === null) {
                $grouped[$showId]['nextRep'] = $repData;
            }
        }

        // Tri : spectacles avec places dispo en premier (allFull=false), puis par date croissante
        uasort($grouped, function ($a, $b) {
            if ($a['allFull'] !== $b['allFull']) {
                return $a['allFull'] <=> $b['allFull'];
            }
            return $a['nextRep']['entity']->getDatetime() <=> $b['nextRep']['entity']->getDatetime();
        });

        return $grouped;
    }

    /**
     * Calcule le nombre de places restantes pour une représentation donnée.
     *
     * @param Representation $representation Représentation à vérifier
     * @return int Nombre de places encore disponibles
     */
    public function getRemainingPlaces(Representation $representation): int
    {
        $bookedMap = $this->representationRepository->findBookedPlacesMap();

        return $representation->getMaxOnlineReservations() - ($bookedMap[$representation->getId()] ?? 0);
    }

    /**
     * Remplit une entité Reservation à partir des données d'un brouillon de session.
     *
     * @param Reservation $reservation Réservation à remplir
     * @param array $draft Données du brouillon (clés : nbAdults, nbChildren, isPMR, lastName, etc.)
     * @return void
     */
    public function hydrateFromDraft(Reservation $reservation, array $draft): void
    {
        $reservation->setNbAdults($draft['nbAdults'] ?? 0);
        $reservation->setNbChildren($draft['nbChildren'] ?? 0);
        $reservation->setIsPMR($draft['isPMR'] ?? false);
        $reservation->setSpectatorLastName($draft['lastName'] ?? '');
        $reservation->setSpectatorFirstName($draft['firstName'] ?? '');
        $reservation->setSpectatorCity($draft['city'] ?? '');
        $reservation->setSpectatorPhone($draft['phone'] ?? '');
        $reservation->setSpectatorEmail($draft['email'] ?? '');
        $reservation->setSpectatorComment($draft['comment'] ?? null);
    }

    /**
     * Construit un brouillon de session à partir d'une réservation et d'un identifiant de représentation.
     *
     * @param int $representationId Identifiant de la représentation sélectionnée
     * @param Reservation $reservation Réservation source
     * @return Draft
     */
    public function buildDraft(int $representationId, Reservation $reservation): array
    {
        return [
            'representation_id' => $representationId,
            'nbAdults' => $reservation->getNbAdults(),
            'nbChildren' => $reservation->getNbChildren(),
            'isPMR' => $reservation->isPMR(),
            'lastName' => $reservation->getSpectatorLastName(),
            'firstName' => $reservation->getSpectatorFirstName(),
            'city' => $reservation->getSpectatorCity(),
            'phone' => $reservation->getSpectatorPhone(),
            'email' => $reservation->getSpectatorEmail(),
            'comment' => $reservation->getSpectatorComment(),
        ];
    }

    /**
     * Calcule le montant total à payer à partir du brouillon et des tarifs de la représentation.
     *
     * @param array $draft Données du brouillon (clés : nbAdults, nbChildren)
     * @param Representation $representation Représentation contenant les tarifs
     * @return float Montant total en euros
     */
    public function computeTotalFromDraft(array $draft, Representation $representation): float
    {
        return ($draft['nbAdults'] * (float) $representation->getAdultPrice())
             + ($draft['nbChildren'] * (float) $representation->getChildPrice());
    }

    /**
     * Initie le processus de paiement HelloAsso et retourne l'URL de redirection.
     *
     * @param array $draft Données du brouillon de réservation
     * @param Representation $representation Représentation concernée
     * @return array{redirectUrl: string, checkoutId: int|string, draft: array}
     */
    public function initiateCheckout(array $draft, Representation $representation): array
    {
        $total = $this->computeTotalFromDraft($draft, $representation);
        $draftToken = bin2hex(random_bytes(32));
        $draft['token'] = $draftToken;

        $checkoutData = $this->helloAssoHandler->createCheckoutIntentFromDraft($draft, $representation, $total, $draftToken);

        return [
            'redirectUrl' => $checkoutData['redirectUrl'],
            'checkoutId' => $checkoutData['id'],
            'draft' => $draft,
        ];
    }

    /**
     * Traite le retour du spectateur après paiement HelloAsso et crée la réservation si le paiement est validé.
     *
     * @param array $draft Données du brouillon stocké en session
     * @param int|string $checkoutId Identifiant du checkout HelloAsso
     * @param Representation $representation Représentation concernée
     * @return Reservation|null Réservation créée ou null si le paiement a échoué
     */
    public function processReturn(array $draft, int|string $checkoutId, Representation $representation): ?Reservation
    {
        $paymentData = $this->helloAssoHandler->verifyCheckout((int) $checkoutId);

        if (!$paymentData) {
            return null;
        }

        // Idempotence : si le webhook a déjà créé la résa avant le retour navigateur, on la retourne sans doublon
        $existing = $this->reservationRepository->findOneBy(['checkoutIntentId' => $checkoutId]);
        if ($existing) {
            return $existing;
        }

        $reservation = $this->reservationService->createFromDraft($draft, $representation);
        $reservation->setCheckoutIntentId((int) $checkoutId);
        $this->helloAssoHandler->recordPayment($reservation, $paymentData);
        $this->reservationService->confirm($reservation);
        $this->reservationMailer->sendConfirmation($reservation);
        $this->logger->info('Réservation #{id} créée et confirmée après paiement HelloAsso.', ['id' => $reservation->getId()]);

        return $reservation;
    }
}
