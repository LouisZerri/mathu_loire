<?php

namespace App\Controller\Admin;

use App\Entity\Show;
use App\Form\ShowType;
use App\Repository\ShowRepository;
use App\Service\Security\AuditLogger;
use App\Service\Admin\ShowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère les actions CRUD sur les spectacles côté administration.
 */
#[Route('/admin/spectacles')]
#[IsGranted('ROLE_BILLETTISTE')]
class ShowController extends AbstractController
{
    /**
     * Liste tous les spectacles enregistrés.
     *
     * @return Response
     */
    #[Route('/', name: 'app_admin_show_index')]
    public function index(ShowRepository $showRepository): Response
    {
        $shows = $showRepository->findAll();

        return $this->render('admin/show/index.html.twig', [
            'shows' => $shows,
        ]);
    }

    /**
     * Crée un nouveau spectacle avec gestion de l'upload d'image.
     *
     * @return Response
     */
    #[Route('/new', name: 'app_admin_show_new')]
    public function new(Request $request, ShowService $showService, SluggerInterface $slugger, AuditLogger $audit): Response
    {
        $show = new Show();
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $showService->create($show, $form, $slugger);

            $audit->log(
                AuditLogger::SHOW_CREATE,
                sprintf('Création du spectacle "%s"', $show->getTitle()),
                'Show',
                $show->getId(),
            );

            $this->addFlash('success', 'Spectacle "' . $show->getTitle() . '" créé.');

            return $this->redirectToRoute('app_admin_show_index');
        }

        return $this->render('admin/show/form.html.twig', [
            'form' => $form,
            'show' => $show,
            'is_new' => true,
        ]);
    }

    /**
     * Modifie un spectacle existant avec remplacement éventuel de l'image.
     *
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_show_edit', requirements: ['id' => '\d+'])]
    public function edit(Show $show, Request $request, ShowService $showService, SluggerInterface $slugger, AuditLogger $audit): Response
    {
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $showService->update($show, $form, $slugger);

            $audit->log(
                AuditLogger::SHOW_UPDATE,
                sprintf('Mise à jour du spectacle "%s"', $show->getTitle()),
                'Show',
                $show->getId(),
            );

            $this->addFlash('success', 'Spectacle "' . $show->getTitle() . '" mis à jour.');

            return $this->redirectToRoute('app_admin_show_index');
        }

        return $this->render('admin/show/form.html.twig', [
            'form' => $form,
            'show' => $show,
            'is_new' => false,
        ]);
    }

    /**
     * Supprime un spectacle ainsi que son image et toutes ses données associées.
     *
     * @return Response
     */
    #[Route('/{id}/delete', name: 'app_admin_show_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Show $show, Request $request, ShowService $showService, AuditLogger $audit): Response
    {
        if ($this->isCsrfTokenValid('delete_show_' . $show->getId(), (string) $request->request->get('_token'))) {
            $title = $show->getTitle();
            $showId = $show->getId();
            $showService->delete($show);
            $audit->log(
                AuditLogger::SHOW_DELETE,
                sprintf('Suppression du spectacle "%s" (et toutes ses données)', $title),
                'Show',
                $showId,
            );
            $this->addFlash('success', 'Spectacle "' . $title . '" et toutes ses données supprimés.');
        }

        return $this->redirectToRoute('app_admin_show_index');
    }
}
