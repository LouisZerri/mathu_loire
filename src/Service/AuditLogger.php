<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public const LOGIN_SUCCESS = 'login.success';
    public const LOGIN_FAILURE = 'login.failure';
    public const LOGOUT = 'logout';

    public const RESERVATION_CREATE = 'reservation.create';
    public const RESERVATION_UPDATE = 'reservation.update';
    public const RESERVATION_CANCEL = 'reservation.cancel';
    public const RESERVATION_REFUND = 'reservation.refund';
    public const RESERVATION_RESEND_EMAIL = 'reservation.resend_email';

    public const REPRESENTATION_CREATE = 'representation.create';
    public const REPRESENTATION_UPDATE = 'representation.update';
    public const REPRESENTATION_CANCEL = 'representation.cancel';

    public const SHOW_CREATE = 'show.create';
    public const SHOW_UPDATE = 'show.update';
    public const SHOW_DELETE = 'show.delete';

    public const USER_CREATE = 'user.create';
    public const USER_UPDATE = 'user.update';
    public const USER_DELETE = 'user.delete';

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function log(
        string $action,
        ?string $summary = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $details = null,
        ?string $forcedEmail = null,
    ): void {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setSummary($summary);
        $log->setTargetType($targetType);
        $log->setTargetId($targetId);
        $log->setDetails($details);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setUser($user);
            $log->setUserEmail($user->getEmail());
        } else {
            $log->setUserEmail($forcedEmail ?? 'anonymous');
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
