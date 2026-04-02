<?php

namespace App\Controller\Admin;

use App\Entity\Show;
use App\Form\ShowType;
use App\Repository\ShowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/spectacles')]
class ShowController extends AbstractController
{
    #[Route('/', name: 'app_admin_show_index')]
    public function index(ShowRepository $showRepository): Response
    {
        $shows = $showRepository->findAll();

        return $this->render('admin/show/index.html.twig', [
            'shows' => $shows,
        ]);
    }

    #[Route('/new', name: 'app_admin_show_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $show = new Show();
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($show);
            $em->flush();

            $this->addFlash('success', 'Spectacle "' . $show->getTitle() . '" créé.');

            return $this->redirectToRoute('app_admin_show_index');
        }

        return $this->render('admin/show/form.html.twig', [
            'form' => $form,
            'show' => $show,
            'is_new' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_show_edit', requirements: ['id' => '\d+'])]
    public function edit(Show $show, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Spectacle "' . $show->getTitle() . '" mis à jour.');

            return $this->redirectToRoute('app_admin_show_index');
        }

        return $this->render('admin/show/form.html.twig', [
            'form' => $form,
            'show' => $show,
            'is_new' => false,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_show_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Show $show, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_show_' . $show->getId(), $request->request->get('_token'))) {
            if ($show->getRepresentations()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer : ce spectacle a des représentations associées.');
            } else {
                $em->remove($show);
                $em->flush();
                $this->addFlash('success', 'Spectacle supprimé.');
            }
        }

        return $this->redirectToRoute('app_admin_show_index');
    }
}
