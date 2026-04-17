<?php

namespace App\Service\Reservation;

/**
 * Exporte les réservations au format CSV compatible Excel.
 */
class ReservationCsvExporter
{
    public function __construct(
        private ReservationService $reservationService,
    ) {
    }

    /**
     * Génère le contenu CSV à partir d'une liste de réservations.
     *
     * @param array $reservations Liste des réservations à exporter
     * @return string Contenu CSV avec en-têtes
     */
    public function export(array $reservations): string
    {
        $csv = "N°;Statut;Nom;Prénom;Ville;Téléphone;Email;Spectacle;Date;Adultes;Enfants;Invitations;Groupes;PMR;Total;Enregistrée le\n";

        foreach ($reservations as $r) {
            $rep = $r->getRepresentation();
            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%s;%s;%s;%d;%d;%d;%d;%s;%s;%s\n",
                $r->getId(),
                $r->getStatus(),
                $this->csvSafe($r->getSpectatorLastName()),
                $this->csvSafe($r->getSpectatorFirstName()),
                $this->csvSafe($r->getSpectatorCity()),
                $this->csvSafe($r->getSpectatorPhone()),
                $this->csvSafe($r->getSpectatorEmail()),
                $this->csvSafe($rep->getShow()->getTitle()),
                $rep->getDatetime()->format('d/m/Y H:i'),
                $r->getNbAdults(), $r->getNbChildren(), $r->getNbInvitations(), $r->getNbGroups(),
                $r->isPMR() ? 'Oui' : 'Non',
                number_format($this->reservationService->computeTotal($r), 2, '.', ''),
                $r->getCreatedAt()->format('d/m/Y H:i'),
            );
        }

        return $csv;
    }

    /**
     * Nettoie une valeur pour l'export CSV en neutralisant les caractères dangereux.
     *
     * @param string $value La valeur brute à assainir
     * @return string La valeur nettoyée
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
