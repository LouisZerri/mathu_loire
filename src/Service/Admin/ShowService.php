<?php

namespace App\Service\Admin;

use App\Entity\Show;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère la logique métier des spectacles :
 * création, mise à jour, suppression et gestion des images uploadées.
 */
class ShowService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Crée un nouveau spectacle avec gestion de l'upload d'image.
     *
     * @param Show $show L'entité spectacle à persister
     * @param FormInterface $form Le formulaire soumis contenant l'image
     * @param SluggerInterface $slugger Le service de slugification pour le nom de fichier
     *
     * @return void
     */
    public function create(Show $show, FormInterface $form, SluggerInterface $slugger): void
    {
        $this->handleImageUpload($form, $show, $slugger);
        $this->em->persist($show);
        $this->em->flush();
    }

    /**
     * Met à jour un spectacle existant avec remplacement éventuel de l'image.
     *
     * @param Show $show L'entité spectacle à mettre à jour
     * @param FormInterface $form Le formulaire soumis contenant l'image
     * @param SluggerInterface $slugger Le service de slugification pour le nom de fichier
     *
     * @return void
     */
    public function update(Show $show, FormInterface $form, SluggerInterface $slugger): void
    {
        $this->handleImageUpload($form, $show, $slugger);
        $this->em->flush();
    }

    /**
     * Supprime un spectacle ainsi que son fichier image associé.
     *
     * @param Show $show L'entité spectacle à supprimer
     *
     * @return void
     */
    public function delete(Show $show): void
    {
        // Supprimer le fichier image
        if ($show->getImageName()) {
            $imagePath = $this->projectDir . '/public/uploads/shows/' . $show->getImageName();
            $this->filesystem->remove($imagePath);
        }

        $this->em->remove($show);
        $this->em->flush();
    }

    /**
     * Traite l'upload d'image du formulaire et met à jour le spectacle.
     *
     * @param FormInterface $form Le formulaire soumis
     * @param Show $show Le spectacle à mettre à jour
     * @param SluggerInterface $slugger Le service de slugification pour le nom de fichier
     *
     * @return void
     */
    private function handleImageUpload(FormInterface $form, Show $show, SluggerInterface $slugger): void
    {
        /** @var UploadedFile|null $imageFile */
        $imageFile = $form->get('imageFile')->getData();

        if (!$imageFile) {
            return;
        }

        // Supprimer l'ancienne image
        if ($show->getImageName()) {
            $oldPath = $this->projectDir . '/public/uploads/shows/' . $show->getImageName();
            $this->filesystem->remove($oldPath);
        }

        $filename = $slugger->slug($show->getTitle()) . '-' . uniqid() . '.' . $imageFile->guessExtension();
        $imageFile->move($this->projectDir . '/public/uploads/shows', $filename);
        $show->setImageName($filename);
    }
}
