<?php

namespace App\Controller\Admin;

use App\Entity\Representation;
use App\Form\RepresentationType;
use App\Repository\RepresentationRepository;
use App\Service\SessionReportPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/representations')]
#[IsGranted('ROLE_ADMIN')]
class RepresentationController extends AbstractController
{
    #[Route('/', name: 'app_admin_representation_index')]
    public function index(RepresentationRepository $representationRepository): Response
    {
        $active = $representationRepository->findBy(
            ['status' => ['active', 'offline']],
            ['datetime' => 'ASC']
        );
        $cancelled = $representationRepository->findBy(
            ['status' => 'cancelled'],
            ['datetime' => 'ASC']
        );

        return $this->render('admin/representation/index.html.twig', [
            'activeRepresentations' => $active,
            'cancelledRepresentations' => $cancelled,
        ]);
    }

    #[Route('/new', name: 'app_admin_representation_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $representation = new Representation();
        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($representation);
            $em->flush();

            $this->addFlash('success', 'Représentation créée.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_representation_edit', requirements: ['id' => '\d+'])]
    public function edit(Representation $representation, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Représentation mise à jour.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => false,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_representation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Representation $representation, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('cancel_rep_' . $representation->getId(), $request->request->get('_token'))) {
            $representation->setStatus('cancelled');
            $em->flush();
            $this->addFlash('success', 'Représentation annulée.');
        }

        return $this->redirectToRoute('app_admin_representation_index');
    }

    #[Route('/{id}/releve', name: 'app_admin_representation_report', requirements: ['id' => '\d+'])]
    public function report(Representation $representation, SessionReportPdfGenerator $pdfGenerator): Response
    {
        $pdf = $pdfGenerator->generate($representation);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="releve-seance-%d.pdf"', $representation->getId()),
        ]);
    }
}
