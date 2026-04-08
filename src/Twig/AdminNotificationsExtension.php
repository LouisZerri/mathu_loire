<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('new_reservations_count', [$this, 'getNewReservationsCount']),
        ];
    }

    public function getNewReservationsCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->reservationRepository->countNewSince($user->getLastReservationsViewedAt());
    }
}
