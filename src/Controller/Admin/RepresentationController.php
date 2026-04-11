<?php

namespace App\Controller\Admin;

use App\Entity\Representation;
use App\Form\RepresentationType;
use App\Repository\RepresentationRepository;
use App\Service\Security\AuditLogger;
use App\Service\Admin\RepresentationService;
use App\Service\Pdf\SessionReportPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère les actions CRUD sur les représentations côté administration.
 */
#[Route('/admin/representations')]
#[IsGranted('ROLE_ADMIN')]
class RepresentationController extends AbstractController
{
    /**
     * Liste les représentations actives et annulées pour la saison sélectionnée.
     *
     * @return Response
     */
    #[Route('/', name: 'app_admin_representation_index')]
    public function index(Request $request, RepresentationRepository $representationRepository, RepresentationService $representationService): Response
    {
        $availableYears = $representationRepository->findAvailableYears();
        $currentYear = (int) date('Y');

        if (empty($availableYears)) {
            $availableYears = [$currentYear];
            $defaultYear = $currentYear;
        } else {
            $defaultYear = in_array($currentYear, $availableYears) ? $currentYear : $availableYears[0];
        }

        $selectedYear = (int) $request->query->get('year', $defaultYear);
        $result = $representationService->getByYear($selectedYear);

        return $this->render('admin/representation/index.html.twig', [
            'activeRepresentations' => $result['active'],
            'cancelledRepresentations' => $result['cancelled'],
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    /**
     * Crée une nouvelle représentation, avec possibilité de dupliquer depuis une existante.
     *
     * @return Response
     */
    #[Route('/new', name: 'app_admin_representation_new')]
    public function new(Request $request, RepresentationService $representationService, AuditLogger $audit): Response
    {
        $representation = new Representation();

        $duplicateFrom = (int) $request->query->get('duplicate_from', 0);
        if ($duplicateFrom) {
            $representationService->prepareDuplicate($duplicateFrom, $representation);
        }

        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $representationService->create($representation);

            $audit->log(
                AuditLogger::REPRESENTATION_CREATE,
                sprintf('Création représentation %s — %s', $representation->getShow()->getTitle(), $representation->getDatetime()->format('d/m/Y H:i')),
                'Representation',
                $representation->getId(),
            );

            $this->addFlash('success', 'Représentation créée.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => true,
        ]);
    }

    /**
     * Modifie les informations d'une représentation existante.
     *
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_representation_edit', requirements: ['id' => '\d+'])]
    public function edit(Representation $representation, Request $request, RepresentationService $representationService, AuditLogger $audit): Response
    {
        $form = $this->createForm(RepresentationType::class, $representation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $representationService->update();

            $audit->log(
                AuditLogger::REPRESENTATION_UPDATE,
                sprintf('Mise à jour représentation #%d (%s)', $representation->getId(), $representation->getShow()->getTitle()),
                'Representation',
                $representation->getId(),
            );

            $this->addFlash('success', 'Représentation mise à jour.');

            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form,
            'representation' => $representation,
            'is_new' => false,
        ]);
    }

    /**
     * Annule une représentation en changeant son statut.
     *
     * @return Response
     */
    #[Route('/{id}/cancel', name: 'app_admin_representation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Representation $representation, Request $request, RepresentationService $representationService, AuditLogger $audit): Response
    {
        if ($this->isCsrfTokenValid('cancel_rep_' . $representation->getId(), (string) $request->request->get('_token'))) {
            $representationService->cancel($representation);
            $audit->log(
                AuditLogger::REPRESENTATION_CANCEL,
                sprintf('Annulation représentation #%d (%s)', $representation->getId(), $representation->getShow()->getTitle()),
                'Representation',
                $representation->getId(),
            );
            $this->addFlash('success', 'Représentation annulée.');
        }

        return $this->redirectToRoute('app_admin_representation_index');
    }

    /**
     * Redirige vers le formulaire de création pré-rempli avec les données d'une représentation existante.
     *
     * @return Response
     */
    #[Route('/{id}/duplicate', name: 'app_admin_representation_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Representation $representation, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('duplicate_rep_' . $representation->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_representation_index');
        }

        return $this->redirectToRoute('app_admin_representation_new', [
            'duplicate_from' => $representation->getId(),
        ]);
    }

    /**
     * Génère et affiche le relevé PDF de séance pour une représentation.
     *
     * @return Response
     */
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
