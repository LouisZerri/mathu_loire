<?php

namespace App\Service\Admin;

use App\Entity\CashRegister;
use App\Entity\Representation;
use App\Entity\User;
use App\Repository\CashRegisterRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère l'ouverture, la clôture et le rapprochement de la caisse pour une représentation.
 */
class CashRegisterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CashRegisterRepository $cashRegisterRepository,
        private ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Ouvre la caisse pour une représentation avec le comptage du fond de caisse.
     *
     * @param Representation $representation Représentation concernée
     * @param array $counts Comptage des billets et pièces (dénomination => quantité)
     * @param User|null $openedBy Utilisateur qui ouvre la caisse
     * @return CashRegister
     */
    public function open(Representation $representation, array $counts, ?User $openedBy): CashRegister
    {
        $existing = $this->cashRegisterRepository->findOneBy(['representation' => $representation]);
        if ($existing) {
            throw new \LogicException('La caisse est déjà ouverte pour cette représentation.');
        }

        $register = new CashRegister();
        $register->setRepresentation($representation);
        $register->setOpeningCounts($counts);
        $register->setOpenedBy($openedBy);

        $this->em->persist($register);
        $this->em->flush();

        return $register;
    }

    /**
     * Clôture la caisse avec le comptage final, les chèques et le montant CB.
     *
     * @param CashRegister $register Caisse à clôturer
     * @param array $counts Comptage billets et pièces à la clôture
     * @param array $cheques Liste des chèques [{amount: float}, ...]
     * @param float $cbTotal Montant total CB
     * @param User|null $closedBy Utilisateur qui clôture
     * @return void
     */
    public function close(CashRegister $register, array $counts, array $cheques, float $cbTotal, ?User $closedBy): void
    {
        $register->setClosingCounts($counts);
        $register->setClosingCheques($cheques);
        $register->setClosingCb((string) $cbTotal);
        $register->setStatus(CashRegister::STATUS_CLOSED);
        $register->setClosedAt(new \DateTimeImmutable());
        $register->setClosedBy($closedBy);

        $this->em->flush();
    }

    /**
     * Calcule le rapprochement entre le théorique (ventes) et le réel (caisse).
     *
     * @param CashRegister $register Caisse à rapprocher
     * @return array
     */
    public function reconcile(CashRegister $register): array
    {
        $representation = $register->getRepresentation();

        // Théorique : somme des paiements espèces + chèques + CB enregistrés dans les résas
        $reservations = $this->reservationRepository->findBy([
            'representation' => $representation,
            'status' => 'validated',
        ]);

        $theoreticalByMethod = ['especes' => 0.0, 'cheque' => 0.0, 'cb' => 0.0, 'helloasso' => 0.0, 'guichet' => 0.0];
        foreach ($reservations as $resa) {
            foreach ($resa->getPayments() as $payment) {
                if ($payment->getType() === 'payment') {
                    $method = $payment->getMethod();
                    $theoreticalByMethod[$method] = ($theoreticalByMethod[$method] ?? 0) + (float) $payment->getAmount();
                }
            }
        }

        $theoreticalCash = $theoreticalByMethod['especes'];
        $theoreticalCheques = $theoreticalByMethod['cheque'];
        $theoreticalCb = $theoreticalByMethod['cb'];
        $theoreticalTotal = $theoreticalCash + $theoreticalCheques + $theoreticalCb;

        // Réel : différence clôture - ouverture pour les espèces, chèques et CB séparément
        $openingTotal = $register->getOpeningTotal();
        $closingCashTotal = $register->getClosingCashTotal();
        $cashReceived = $closingCashTotal - $openingTotal;
        $chequesReceived = $register->getClosingChequesTotal();
        $cbReceived = (float) ($register->getClosingCb() ?? 0);
        $totalReceived = $cashReceived + $chequesReceived + $cbReceived;

        return [
            'theoreticalCash' => $theoreticalCash,
            'theoreticalCheques' => $theoreticalCheques,
            'theoreticalCb' => $theoreticalCb,
            'theoreticalTotal' => $theoreticalTotal,
            'theoreticalHelloasso' => $theoreticalByMethod['helloasso'],
            'cashReceived' => $cashReceived,
            'chequesReceived' => $chequesReceived,
            'cbReceived' => $cbReceived,
            'totalReceived' => $totalReceived,
            'openingTotal' => $openingTotal,
            'closingCashTotal' => $closingCashTotal,
            'difference' => round($totalReceived - $theoreticalTotal, 2),
        ];
    }
}
