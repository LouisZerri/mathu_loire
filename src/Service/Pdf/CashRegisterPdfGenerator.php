<?php

namespace App\Service\Pdf;

use App\Entity\CashRegister;
use App\Service\Admin\CashRegisterService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le PDF de la feuille de caisse avec fond de caisse, clôture et rapprochement.
 */
class CashRegisterPdfGenerator
{
    public function __construct(
        private Environment $twig,
        private CashRegisterService $cashRegisterService,
    ) {
    }

    /**
     * Génère le PDF de la feuille de caisse.
     *
     * @param CashRegister $register Caisse clôturée
     * @return string Contenu binaire du PDF généré
     */
    public function generate(CashRegister $register): string
    {
        $reconciliation = $this->cashRegisterService->reconcile($register);

        $html = $this->twig->render('pdf/cash_register.html.twig', [
            'register' => $register,
            'representation' => $register->getRepresentation(),
            'reconciliation' => $reconciliation,
            'bills' => CashRegister::DENOMINATIONS_BILLS,
            'coins' => CashRegister::DENOMINATIONS_COINS,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
