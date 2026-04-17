<?php

namespace App\Controller\Admin;

use App\Entity\CashRegister;
use App\Entity\Representation;
use App\Entity\User;
use App\Repository\CashRegisterRepository;
use App\Service\Admin\CashRegisterService;
use App\Service\Pdf\CashRegisterPdfGenerator;
use App\Service\Security\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère l'ouverture, la clôture et la consultation de la caisse par représentation.
 */
#[Route('/admin/caisse')]
#[IsGranted('ROLE_BILLETTISTE')]
class CashRegisterController extends AbstractController
{
    /**
     * Affiche le formulaire d'ouverture ou l'état de la caisse pour une représentation.
     *
     * @return Response
     */
    #[Route('/{id}', name: 'app_admin_cash_register', requirements: ['id' => '\d+'])]
    public function index(
        Representation $representation,
        CashRegisterRepository $cashRegisterRepository,
        CashRegisterService $cashRegisterService,
    ): Response {
        $register = $cashRegisterRepository->findOneBy(['representation' => $representation]);

        $reconciliation = null;
        if ($register && $register->getStatus() === CashRegister::STATUS_CLOSED) {
            $reconciliation = $cashRegisterService->reconcile($register);
        }

        return $this->render('admin/cash_register/index.html.twig', [
            'representation' => $representation,
            'register' => $register,
            'reconciliation' => $reconciliation,
        ]);
    }

    /**
     * Ouvre la caisse avec le comptage du fond de caisse.
     *
     * @return Response
     */
    #[Route('/{id}/open', name: 'app_admin_cash_register_open', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function open(
        Representation $representation,
        Request $request,
        CashRegisterService $cashRegisterService,
        AuditLogger $audit,
    ): Response {
        if (!$this->isCsrfTokenValid('cash_open_' . $representation->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_cash_register', ['id' => $representation->getId()]);
        }

        $counts = $this->parseCounts($request);
        $user = $this->getUser();

        try {
            $cashRegisterService->open($representation, $counts, $user instanceof User ? $user : null);
            $audit->log(AuditLogger::REPRESENTATION_UPDATE, sprintf('Ouverture de caisse (représentation #%d)', $representation->getId()), 'Representation', $representation->getId());
            $this->addFlash('success', 'Caisse ouverte avec un fond de ' . number_format(CashRegister::computeCountTotal($counts), 2, ',', ' ') . ' €.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_cash_register', ['id' => $representation->getId()]);
    }

    /**
     * Clôture la caisse avec le comptage final.
     *
     * @return Response
     */
    #[Route('/{id}/close', name: 'app_admin_cash_register_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(
        Representation $representation,
        Request $request,
        CashRegisterRepository $cashRegisterRepository,
        CashRegisterService $cashRegisterService,
        AuditLogger $audit,
    ): Response {
        if (!$this->isCsrfTokenValid('cash_close_' . $representation->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_admin_cash_register', ['id' => $representation->getId()]);
        }

        $register = $cashRegisterRepository->findOneBy(['representation' => $representation]);
        if (!$register || $register->getStatus() !== CashRegister::STATUS_OPEN) {
            $this->addFlash('error', 'La caisse n\'est pas ouverte.');
            return $this->redirectToRoute('app_admin_cash_register', ['id' => $representation->getId()]);
        }

        $counts = $this->parseCounts($request, 'closing_');
        $cbTotal = (float) $request->request->get('closing_cb', 0);

        // Parser les chèques
        $chequeAmounts = $request->request->all('cheque_amounts');
        $cheques = [];
        foreach ($chequeAmounts as $amount) {
            $a = (float) $amount;
            if ($a > 0) {
                $cheques[] = ['amount' => $a];
            }
        }

        $user = $this->getUser();
        $cashRegisterService->close($register, $counts, $cheques, $cbTotal, $user instanceof User ? $user : null);
        $audit->log(AuditLogger::REPRESENTATION_UPDATE, sprintf('Clôture de caisse (représentation #%d)', $representation->getId()), 'Representation', $representation->getId());
        $this->addFlash('success', 'Caisse clôturée.');

        return $this->redirectToRoute('app_admin_cash_register', ['id' => $representation->getId()]);
    }

    /**
     * Génère le PDF de la feuille de caisse clôturée.
     *
     * @return Response
     */
    #[Route('/{id}/pdf', name: 'app_admin_cash_register_pdf', requirements: ['id' => '\d+'])]
    public function pdf(
        CashRegister $register,
        CashRegisterPdfGenerator $pdfGenerator,
    ): Response {
        if ($register->getStatus() !== CashRegister::STATUS_CLOSED) {
            $this->addFlash('error', 'La caisse doit être clôturée pour générer le PDF.');
            return $this->redirectToRoute('app_admin_cash_register', ['id' => $register->getRepresentation()->getId()]);
        }

        $pdf = $pdfGenerator->generate($register);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="feuille-de-caisse-' . $register->getId() . '.pdf"',
        ]);
    }

    /**
     * Parse les quantités de billets et pièces depuis la requête.
     */
    private function parseCounts(Request $request, string $prefix = ''): array
    {
        $counts = [];
        $denominations = array_merge(CashRegister::DENOMINATIONS_BILLS, CashRegister::DENOMINATIONS_COINS);

        foreach ($denominations as $d) {
            $key = $prefix . 'denom_' . str_replace('.', '_', (string) $d);
            $counts[(string) $d] = max(0, (int) $request->request->get($key, 0));
        }

        return $counts;
    }
}
