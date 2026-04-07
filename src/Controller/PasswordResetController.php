<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgot(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        PasswordResetMailer $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $user = $email ? $userRepository->findOneBy(['email' => $email]) : null;

            if ($user) {
                $user->setResetToken(bin2hex(random_bytes(32)));
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                try {
                    $mailer->sendResetLink($user);
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

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
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
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
