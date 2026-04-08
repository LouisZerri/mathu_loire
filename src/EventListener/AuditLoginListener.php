<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuditLoginListener
{
    public function __construct(private AuditLogger $auditLogger){}

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(): void
    {
        $this->auditLogger->log(AuditLogger::LOGIN_SUCCESS, 'Connexion réussie');
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $attempted = (string) $request->request->get('_username', 'inconnu');
        $reason = $event->getException()->getMessageKey();

        $this->auditLogger->log(
            AuditLogger::LOGIN_FAILURE,
            sprintf('Échec de connexion (%s)', $attempted),
            details: ['reason' => $reason],
            forcedEmail: $attempted,
        );
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token) {
            $this->auditLogger->log(AuditLogger::LOGOUT, 'Déconnexion');
        }
    }
}
