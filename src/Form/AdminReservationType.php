<?php

namespace App\Form;

use App\Entity\Representation;
use App\Entity\Reservation;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('representation', EntityType::class, [
                'class' => Representation::class,
                'choice_label' => function (Representation $rep) {
                    $dt = \DateTime::createFromInterface($rep->getDatetime());
                    $dt->setTimezone(new \DateTimeZone('Europe/Paris'));
                    return $rep->getShow()->getTitle() . ' — ' . $dt->format('d/m/Y H:i');
                },
                'label' => 'Représentation',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('r')
                        ->where('r.status = :status')
                        ->setParameter('status', 'active')
                        ->orderBy('r.datetime', 'ASC');
                },
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Validée' => 'validated',
                    'En attente' => 'pending',
                    'Modifiée' => 'modified',
                    'Annulée' => 'cancelled',
                ],
            ])
            ->add('nbAdults', IntegerType::class, [
                'label' => 'Adultes',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0],
            ])
            ->add('nbChildren', IntegerType::class, [
                'label' => 'Enfants',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0],
            ])
            ->add('nbInvitations', IntegerType::class, [
                'label' => 'Invitations',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0],
            ])
            ->add('nbGroups', IntegerType::class, [
                'label' => 'Groupes (tarif réduit)',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0],
            ])
            ->add('isPMR', CheckboxType::class, [
                'label' => 'PMR',
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
                'label' => 'Commentaire spectateur',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('adminComment', TextareaType::class, [
                'label' => 'Commentaire administrateur',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);

        if ($options['show_payment_method']) {
            $builder->add('paymentMethod', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Pas de paiement immédiat',
                'choices' => [
                    'Espèces' => 'especes',
                    'Chèque' => 'cheque',
                    'Carte bancaire' => 'cb',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'show_payment_method' => false,
        ]);
    }
}
