<?php

namespace App\Service\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gère la logique métier des utilisateurs :
 * création, mise à jour et suppression avec hashage du mot de passe.
 */
class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Crée un nouvel utilisateur avec hashage du mot de passe.
     *
     * @param User $user L'entité utilisateur à persister
     * @param string $plainPassword Le mot de passe en clair à hasher
     *
     * @return void
     */
    public function create(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * Met à jour un utilisateur avec changement optionnel du mot de passe.
     *
     * @param User $user L'entité utilisateur à mettre à jour
     * @param string|null $plainPassword Le nouveau mot de passe en clair, ou null pour ne pas changer
     *
     * @return void
     */
    public function update(User $user, ?string $plainPassword): void
    {
        if ($plainPassword) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->em->flush();
    }

    /**
     * Supprime un utilisateur de la base de données.
     *
     * @param User $user L'entité utilisateur à supprimer
     *
     * @return void
     */
    public function delete(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }
}
