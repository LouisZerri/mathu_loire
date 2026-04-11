<?php

namespace App\Controller;

use App\Entity\Show;
use App\Repository\RepresentationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche le détail d'un spectacle côté public avec ses représentations à venir.
 */
class ShowController extends AbstractController
{
    /**
     * Affiche la fiche détaillée d'un spectacle avec la jauge de chaque représentation future.
     *
     * @return Response
     */
    #[Route('/spectacle/{id}', name: 'app_show_detail', requirements: ['id' => '\d+'])]
    public function detail(Show $show, RepresentationRepository $representationRepository): Response
    {
        $representations = $representationRepository->createQueryBuilder('r')
            ->where('r.show = :show')
            ->andWhere('r.datetime > :now')
            ->andWhere('r.status = :status')
            ->setParameter('show', $show)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'active')
            ->orderBy('r.datetime', 'ASC')
            ->getQuery()
            ->getResult();

        $bookedMap = $representationRepository->findBookedPlacesMap();

        $repsWithJauge = [];
        foreach ($representations as $rep) {
            $booked = $bookedMap[$rep->getId()] ?? 0;
            $repsWithJauge[] = [
                'entity' => $rep,
                'booked' => $booked,
                'remaining' => $rep->getMaxOnlineReservations() - $booked,
                'isFull' => $booked >= $rep->getMaxOnlineReservations(),
            ];
        }

        return $this->render('public/show/detail.html.twig', [
            'show' => $show,
            'representations' => $repsWithJauge,
        ]);
    }
}
