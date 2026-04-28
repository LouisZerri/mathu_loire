<?php

namespace App\EventListener;

use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * En APP_ENV=test_e2e uniquement : intercepte tous les emails envoyés par Symfony Mailer
 * et les écrit dans var/e2e-mailbox.json pour permettre aux tests Playwright d'asserter
 * sur les emails envoyés (pas de service externe type Mailpit, pas de réseau).
 *
 * Le listener est enregistré uniquement en test_e2e via config/services.yaml — pas d'attribut
 * AsEventListener pour qu'il ne soit pas auto-discovery en dev/prod.
 */
class E2eMailboxListener
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        if ($event->isQueued()) {
            return;
        }

        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $entry = [
            'to' => array_map(fn ($a) => $a->getAddress(), $message->getTo()),
            'subject' => $message->getSubject(),
            'sentAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $file = $this->projectDir . '/var/e2e-mailbox.json';
        $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) ?: [] : [];
        $existing[] = $entry;
        file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
    }
}
