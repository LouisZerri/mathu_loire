<?php

namespace App\Form;

use App\Entity\Representation;
use App\Entity\Show;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepresentationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('show', EntityType::class, [
                'class' => Show::class,
                'choice_label' => 'title',
                'label' => 'Spectacle',
            ])
            ->add('datetime', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => 'active',
                    'Annulé' => 'cancelled',
                    'Hors-ligne' => 'offline',
                ],
            ])
            ->add('maxOnlineReservations', IntegerType::class, [
                'label' => 'Réservations max en ligne',
                'attr' => ['min' => 0],
            ])
            ->add('venueCapacity', IntegerType::class, [
                'label' => 'Jauge de la salle',
                'attr' => ['min' => 0],
            ])
            ->add('adultPrice', NumberType::class, [
                'label' => 'Tarif adulte (€)',
                'scale' => 2,
            ])
            ->add('childPrice', NumberType::class, [
                'label' => 'Tarif enfant (€)',
                'scale' => 2,
            ])
            ->add('groupPrice', NumberType::class, [
                'label' => 'Tarif groupe (€)',
                'scale' => 2,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Representation::class,
        ]);
    }
}
