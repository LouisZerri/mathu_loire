<?php

namespace App\Service\Admin;

use App\Entity\Representation;
use App\Repository\RepresentationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère la logique métier des représentations :
 * récupération par année, création, mise à jour, annulation et duplication.
 */
class RepresentationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RepresentationRepository $representationRepository,
    ) {
    }

    /**
     * Récupère les représentations d'une année et les sépare en actives et annulées.
     *
     * @param int $year L'année de la saison à afficher
     *
     * @return array{active: Representation[], cancelled: Representation[]} Les représentations triées par statut
     */
    public function getByYear(int $year): array
    {
        $representations = $this->representationRepository->findByYear($year);

        $active = [];
        $cancelled = [];
        foreach ($representations as $rep) {
            if ($rep->getStatus() === 'cancelled') {
                $cancelled[] = $rep;
            } else {
                $active[] = $rep;
            }
        }

        return ['active' => $active, 'cancelled' => $cancelled];
    }

    /**
     * Persiste une nouvelle représentation en base de données.
     *
     * @param Representation $representation L'entité représentation à créer
     *
     * @return void
     */
    public function create(Representation $representation): void
    {
        $this->em->persist($representation);
        $this->em->flush();
    }

    /**
     * Enregistre les modifications d'une représentation existante.
     *
     * @return void
     */
    public function update(): void
    {
        $this->em->flush();
    }

    /**
     * Annule une représentation en changeant son statut.
     *
     * @param Representation $representation L'entité représentation à annuler
     *
     * @return void
     */
    public function cancel(Representation $representation): void
    {
        $representation->setStatus('cancelled');
        $this->em->flush();
    }

    /**
     * Pré-remplit une représentation cible avec les données d'une représentation source.
     *
     * @param int $sourceId L'identifiant de la représentation source
     * @param Representation $target La représentation cible à pré-remplir
     *
     * @return void
     */
    public function prepareDuplicate(int $sourceId, Representation $target): void
    {
        $source = $this->representationRepository->find($sourceId);

        if (!$source) {
            return;
        }

        $target->setShow($source->getShow());
        $target->setDatetime((clone $source->getDatetime())->modify('+7 days'));
        $target->setStatus('active');
        $target->setMaxOnlineReservations($source->getMaxOnlineReservations());
        $target->setVenueCapacity($source->getVenueCapacity());
        $target->setAdultPrice($source->getAdultPrice());
        $target->setChildPrice($source->getChildPrice());
        $target->setGroupPrice($source->getGroupPrice());
    }
}
