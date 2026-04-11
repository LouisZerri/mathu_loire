<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche les pages juridiques du site (mentions légales et politique de confidentialité).
 */
class LegalController extends AbstractController
{
    /**
     * Affiche la page des mentions légales.
     *
     * @return Response
     */
    #[Route('/mentions-legales', name: 'app_legal')]
    public function legal(): Response
    {
        return $this->render('public/legal.html.twig');
    }

    /**
     * Affiche la page de politique de confidentialité.
     *
     * @return Response
     */
    #[Route('/politique-de-confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('public/privacy.html.twig');
    }
}
