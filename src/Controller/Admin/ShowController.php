<?php

namespace App\Controller\Admin;

use App\Entity\Show;
use App\Form\ShowType;
use App\Repository\ShowRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/spectacles')]
#[IsGranted('ROLE_BILLETTISTE')]
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
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, AuditLogger $audit): Response
    {
        $show = new Show();
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $show, $slugger);
            $em->persist($show);
            $em->flush();

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

    #[Route('/{id}/edit', name: 'app_admin_show_edit', requirements: ['id' => '\d+'])]
    public function edit(Show $show, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, AuditLogger $audit): Response
    {
        $form = $this->createForm(ShowType::class, $show);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $show, $slugger);
            $em->flush();

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

    #[Route('/{id}/delete', name: 'app_admin_show_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Show $show, Request $request, EntityManagerInterface $em, AuditLogger $audit): Response
    {
        if ($this->isCsrfTokenValid('delete_show_' . $show->getId(), (string) $request->request->get('_token'))) {
            $title = $show->getTitle();
            $showId = $show->getId();
            if ($show->getImageName()) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/shows/' . $show->getImageName();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $em->remove($show);
            $em->flush();
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

    private function handleImageUpload(FormInterface $form, Show $show, SluggerInterface $slugger): void
    {
        /** @var UploadedFile|null $imageFile */
        $imageFile = $form->get('imageFile')->getData();

        if (!$imageFile) {
            return;
        }

        // Supprimer l'ancienne image
        if ($show->getImageName()) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/shows/' . $show->getImageName();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $filename = $slugger->slug($show->getTitle()) . '-' . uniqid() . '.' . $imageFile->guessExtension();
        $imageFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/shows', $filename);
        $show->setImageName($filename);
    }
}
