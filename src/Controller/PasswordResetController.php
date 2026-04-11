<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\Security\PasswordResetMailer;
use App\Service\Security\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gère le processus de réinitialisation du mot de passe (demande et validation).
 */
class PasswordResetController extends AbstractController
{
    /**
     * Traite la demande de réinitialisation de mot de passe et envoie le lien par email.
     *
     * @return Response
     */
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgot(
        Request $request,
        UserRepository $userRepository,
        PasswordResetService $resetService,
        PasswordResetMailer $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $result = $resetService->requestReset($email, $userRepository);

            if ($result) {
                try {
                    $mailer->sendResetLink($result['user'], $result['rawToken']);
                } catch (\Exception $e) {
                    // ne pas révéler l'erreur
                }
            }

            // Toujours afficher le même message pour ne pas révéler quels emails existent
            $this->addFlash('success', 'Si cet email est associé à un compte, un lien de réinitialisation vient d\'être envoyé.');

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    /**
     * Valide le jeton de réinitialisation et permet la saisie du nouveau mot de passe.
     *
     * @param string $token Le jeton de réinitialisation reçu par email
     *
     * @return Response
     */
    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepository,
        PasswordResetService $resetService,
    ): Response {
        $user = $resetService->validateToken($token, $userRepository);

        if (!$user) {
            $this->addFlash('error', 'Ce lien est invalide ou a expiré.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                $resetService->resetPassword($user, $password);

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
