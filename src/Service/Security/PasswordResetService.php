<?php

namespace App\Service\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gère la logique métier de réinitialisation de mot de passe :
 * génération de jeton, validation et changement de mot de passe.
 */
class PasswordResetService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Génère un jeton de réinitialisation pour l'utilisateur associé à l'email.
     *
     * @param string $email L'adresse email de l'utilisateur
     * @param UserRepository $userRepository Le dépôt utilisateur pour la recherche
     *
     * @return array{user: User, rawToken: string}|null Les données du jeton ou null si utilisateur introuvable
     */
    public function requestReset(string $email, UserRepository $userRepository): ?array
    {
        $user = $email ? $userRepository->findOneBy(['email' => $email]) : null;

        if (!$user) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(32));
        $user->setResetToken(hash('sha256', $rawToken));
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        return ['user' => $user, 'rawToken' => $rawToken];
    }

    /**
     * Valide un jeton de réinitialisation et retourne l'utilisateur associé.
     *
     * @param string $token Le jeton brut reçu par email
     * @param UserRepository $userRepository Le dépôt utilisateur pour la recherche
     *
     * @return User|null L'utilisateur si le jeton est valide, null sinon
     */
    public function validateToken(string $token, UserRepository $userRepository): ?User
    {
        $user = $userRepository->findOneBy(['resetToken' => hash('sha256', $token)]);

        if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    /**
     * Réinitialise le mot de passe de l'utilisateur et supprime le jeton.
     *
     * @param User $user L'utilisateur dont le mot de passe doit être changé
     * @param string $password Le nouveau mot de passe en clair
     *
     * @return void
     */
    public function resetPassword(User $user, string $password): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->em->flush();
    }
}
