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
     * Ajoute un paiement partiel ou total à une réservation.
     *
     * @param Reservation $reservation Réservation concernée
     * @param string $method Mode de paiement (especes, cheque, cb, helloasso, guichet)
     * @param float $amount Montant en euros
     * @return Payment Le paiement créé
     */
    public function addPayment(Reservation $reservation, string $method, float $amount): Payment
    {
        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setMethod($method);
        $payment->setAmount((string) $amount);
        $payment->setType('payment');
        $payment->setTransactionId($method . '_' . $reservation->getId() . '_' . time());
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /**
     * Modifie le mode de paiement d'un enregistrement existant.
     *
     * @param Payment $payment Paiement à modifier
     * @param string $method Nouveau mode de paiement
     * @param float|null $amount Nouveau montant (null = inchangé)
     * @return void
     */
    public function editPayment(Payment $payment, string $method, ?float $amount = null): void
    {
        $payment->setMethod($method);
        if ($amount !== null) {
            $payment->setAmount((string) $amount);
        }
        $this->em->flush();
    }

    /**
     * Supprime un enregistrement de paiement.
     *
     * @param Payment $payment Paiement à supprimer
     * @return void
     */
    public function deletePayment(Payment $payment): void
    {
        $this->em->remove($payment);
        $this->em->flush();
    }

    /**
     * Calcule le montant restant à payer pour une réservation.
     *
     * @param Reservation $reservation Réservation à vérifier
     * @return float Montant restant (0 si tout est payé)
     */
    public function getRemainingToPay(Reservation $reservation): float
    {
        $total = $this->computeTotal($reservation);
        $paid = 0.0;

        foreach ($reservation->getPayments() as $payment) {
            if ($payment->getType() === 'payment') {
                $paid += (float) $payment->getAmount();
            }
        }

        return max(0, $total - $paid);
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
             + ($reservation->getNbChildren() * (float) $representation->getChildPrice())
             + ($reservation->getNbGroups() * (float) ($representation->getGroupPrice() ?? $representation->getAdultPrice()));
    }

}
