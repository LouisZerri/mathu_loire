<?php

namespace App\Service\Reservation;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\Representation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère le cycle de vie des réservations : création, confirmation, annulation et calcul du montant.
 */
class ReservationService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Initialise et persiste une nouvelle réservation en statut pending.
     *
     * @param Reservation $reservation Réservation à initialiser
     * @param Representation $representation Représentation associée
     * @return void
     */
    public function create(Reservation $reservation, Representation $representation): void
    {
        $reservation->setRepresentation($representation);
        $reservation->setStatus('pending');
        $reservation->setNbInvitations(0);
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();
    }

    /**
     * Crée une réservation complète depuis un brouillon session (après paiement).
     *
     * @param array $draft Données du formulaire (nbAdults, nbChildren, lastName, email, etc.)
     * @param Representation $representation Représentation associée
     * @return Reservation La réservation persistée
     */
    public function createFromDraft(array $draft, Representation $representation): Reservation
    {
        $reservation = new Reservation();
        $reservation->setRepresentation($representation);
        $reservation->setStatus('pending');
        $reservation->setNbAdults((int) ($draft['nbAdults'] ?? 0));
        $reservation->setNbChildren((int) ($draft['nbChildren'] ?? 0));
        $reservation->setNbInvitations(0);
        $reservation->setIsPMR((bool) ($draft['isPMR'] ?? false));
        $reservation->setSpectatorLastName($draft['lastName'] ?? '');
        $reservation->setSpectatorFirstName($draft['firstName'] ?? '');
        $reservation->setSpectatorCity($draft['city'] ?? '');
        $reservation->setSpectatorPhone($draft['phone'] ?? '');
        $reservation->setSpectatorEmail($draft['email'] ?? '');
        $reservation->setSpectatorComment($draft['comment'] ?? null);
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    /**
     * Persiste les modifications en base.
     *
     * @return void
     */
    public function save(): void
    {
        $this->em->flush();
    }

    /**
     * Confirme une réservation en passant son statut à validated.
     *
     * @param Reservation $reservation Réservation à confirmer
     * @return void
     */
    public function confirm(Reservation $reservation): void
    {
        $reservation->setStatus('validated');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * Annule une réservation et libère tous les sièges assignés.
     *
     * @param Reservation $reservation Réservation à annuler
     * @return void
     */
    public function cancel(Reservation $reservation): void
    {
        $reservation->setStatus('cancelled');
        $reservation->setUpdatedAt(new \DateTimeImmutable());

        foreach ($reservation->getSeatAssignments() as $assignment) {
            if ($assignment->getStatus() === 'assigned') {
                $this->em->remove($assignment);
            }
        }

        $this->em->flush();
    }

    /**
     * Crée manuellement une réservation depuis l'interface d'administration, avec paiement optionnel.
     *
     * @param Reservation $reservation Réservation à persister
     * @param User|null $createdBy Utilisateur ayant créé la réservation
     * @param string|null $paymentMethod Mode de paiement (especes, cheque, cb) ou null si pas de paiement immédiat
     * @return void
     */
    public function createManual(Reservation $reservation, ?User $createdBy, ?string $paymentMethod = null): void
    {
        $reservation->setStatus('validated');
        $reservation->setCreatedBy($createdBy);
        $reservation->setToken(bin2hex(random_bytes(32)));
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();

        if ($paymentMethod) {
            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setMethod($paymentMethod);
            $payment->setAmount((string) $this->computeTotal($reservation));
            $payment->setType('payment');
            $payment->setTransactionId($paymentMethod . '_' . $reservation->getId());
            $payment->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($payment);
            $this->em->flush();
        }
    }

    /**
     * Exporte une liste de réservations au format CSV compatible Excel.
     *
     * @param array $reservations Liste des réservations à exporter
     * @return string Contenu CSV avec en-têtes
     */
    public function exportCsv(array $reservations): string
    {
        $csv = "N°;Statut;Nom;Prénom;Ville;Téléphone;Email;Spectacle;Date;Adultes;Enfants;Invitations;PMR;Total;Enregistrée le\n";

        foreach ($reservations as $r) {
            $rep = $r->getRepresentation();
            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%s;%s;%s;%d;%d;%d;%s;%s;%s\n",
                $r->getId(),
                $r->getStatus(),
                $this->csvSafe($r->getSpectatorLastName()),
                $this->csvSafe($r->getSpectatorFirstName()),
                $this->csvSafe($r->getSpectatorCity()),
                $this->csvSafe($r->getSpectatorPhone()),
                $this->csvSafe($r->getSpectatorEmail()),
                $this->csvSafe($rep->getShow()->getTitle()),
                $rep->getDatetime()->format('d/m/Y H:i'),
                $r->getNbAdults(), $r->getNbChildren(), $r->getNbInvitations(),
                $r->isPMR() ? 'Oui' : 'Non',
                number_format($this->computeTotal($r), 2, '.', ''),
                $r->getCreatedAt()->format('d/m/Y H:i'),
            );
        }

        return $csv;
    }

    /**
     * Enregistre un paiement manuel (au guichet) pour une réservation.
     *
     * @param Reservation $reservation Réservation à marquer comme payée
     * @return void
     */
    public function markAsPaid(Reservation $reservation): void
    {
        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setMethod('guichet');
        $payment->setAmount((string) $this->computeTotal($reservation));
        $payment->setType('payment');
        $payment->setTransactionId('guichet_' . $reservation->getId());
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();
    }

    /**
     * Calcule le montant total d'une réservation (adultes + enfants).
     *
     * @param Reservation $reservation Réservation à calculer
     * @return float Montant total en euros
     */
    public function computeTotal(Reservation $reservation): float
    {
        $representation = $reservation->getRepresentation();

        return ($reservation->getNbAdults() * (float) $representation->getAdultPrice())
             + ($reservation->getNbChildren() * (float) $representation->getChildPrice());
    }

    /**
     * Nettoie une valeur pour l'export CSV en neutralisant les caractères dangereux.
     *
     * @param string $value La valeur brute à assainir
     * @return string La valeur nettoyée pour insertion CSV
     */
    private function csvSafe(string $value): string
    {
        $value = str_replace(';', ',', $value);

        if (isset($value[0]) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        return $value;
    }
}
