<?php

namespace App\Form;

use App\Entity\Show;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ShowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('synopsis', TextareaType::class, [
                'label' => 'Synopsis',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('author', TextType::class, [
                'label' => 'Auteur',
                'required' => false,
            ])
            ->add('director', TextType::class, [
                'label' => 'Mise en scène',
                'required' => false,
            ])
            ->add('duration', TextType::class, [
                'label' => 'Durée',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 1h30 avec entracte'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Affiche',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Format accepté : JPG, PNG ou WebP.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Show::class,
        ]);
    }
}
