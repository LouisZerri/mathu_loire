<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nbAdults', IntegerType::class, [
                'label' => 'Nombre d\'adultes',
                'attr' => ['min' => 0, 'max' => 20],
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0),
                ],
            ])
            ->add('nbChildren', IntegerType::class, [
                'label' => 'Nombre d\'enfants (- de 12 ans)',
                'attr' => ['min' => 0, 'max' => 20],
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0),
                ],
            ])
            ->add('isPMR', CheckboxType::class, [
                'label' => 'Personne à mobilité réduite (PMR)',
                'required' => false,
            ])
            ->add('spectatorLastName', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('spectatorFirstName', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('spectatorCity', TextType::class, [
                'label' => 'Ville',
            ])
            ->add('spectatorPhone', TelType::class, [
                'label' => 'Téléphone',
            ])
            ->add('spectatorEmail', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('spectatorComment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Informations complémentaires...'],
            ])
            ->add('rgpdConsent', CheckboxType::class, [
                'label' => 'J\'accepte que mes données personnelles soient traitées pour la gestion de ma réservation.',
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue(message: 'Vous devez accepter la politique de confidentialité.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
