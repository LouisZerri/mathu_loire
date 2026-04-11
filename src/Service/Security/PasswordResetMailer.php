<?php

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Envoie l'email de réinitialisation de mot de passe avec un lien sécurisé.
 */
class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $mailerFrom,
    ) {
    }

    /**
     * Envoie l'email contenant le lien de réinitialisation au membre.
     *
     * @param User $user Utilisateur demandant la réinitialisation
     * @param string $rawToken Jeton brut (non hashé) à inclure dans le lien
     * @return void
     */
    public function sendResetLink(User $user, string $rawToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->twig->render('email/password_reset.html.twig', [
            'user' => $user,
            'resetUrl' => $resetUrl,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - Les Mathu\'Loire')
            ->html($html);

        $this->mailer->send($email);
    }
}
