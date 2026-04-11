<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Gère l'authentification des utilisateurs (connexion et déconnexion).
 */
class SecurityController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion ou redirige vers le tableau de bord si déjà connecté.
     *
     * @return Response
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Point de sortie pour la déconnexion, intercepté par le pare-feu Symfony.
     *
     * @return void
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
    }
}
