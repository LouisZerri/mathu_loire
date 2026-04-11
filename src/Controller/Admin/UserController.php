<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\Security\AuditLogger;
use App\Service\Admin\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère les actions CRUD sur les utilisateurs de l'application côté administration.
 */
#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    /**
     * Liste tous les utilisateurs enregistrés.
     *
     * @return Response
     */
    #[Route('/', name: 'app_admin_user_index')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Crée un nouvel utilisateur avec hashage du mot de passe.
     *
     * @return Response
     */
    #[Route('/new', name: 'app_admin_user_new')]
    public function new(
        Request $request,
        UserService $userService,
        AuditLogger $audit,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $userService->create($user, $plainPassword);

            $audit->log(
                AuditLogger::USER_CREATE,
                sprintf('Création utilisateur %s (%s)', $user->getEmail(), implode(',', $user->getRoles())),
                'User',
                $user->getId(),
            );

            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'is_new' => true,
        ]);
    }

    /**
     * Modifie un utilisateur existant avec mise à jour optionnelle du mot de passe.
     *
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'])]
    public function edit(
        User $user,
        Request $request,
        UserService $userService,
        AuditLogger $audit,
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $userService->update($user, $plainPassword);

            $audit->log(
                AuditLogger::USER_UPDATE,
                sprintf('Mise à jour utilisateur %s%s', $user->getEmail(), $plainPassword ? ' (mot de passe changé)' : ''),
                'User',
                $user->getId(),
            );

            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'is_new' => false,
        ]);
    }

    /**
     * Supprime un utilisateur après vérification qu'il ne s'agit pas du compte connecté.
     *
     * @return Response
     */
    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, UserService $userService, AuditLogger $audit): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $email = $user->getEmail();
            $userId = $user->getId();
            $userService->delete($user);
            $audit->log(
                AuditLogger::USER_DELETE,
                sprintf('Suppression utilisateur %s', $email),
                'User',
                $userId,
            );
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }
}
