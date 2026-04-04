<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:anonymize-reservations',
    description: 'Anonymise les données personnelles des réservations dont la représentation date de plus de 12 mois.',
)]
class AnonymizeOldReservationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('months', 'm', InputOption::VALUE_OPTIONAL, 'Nombre de mois après lequel anonymiser', 12);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $months = (int) $input->getOption('months');

        $date = new \DateTime("-{$months} months");

        $count = $this->em->createQuery(
            'UPDATE App\Entity\Reservation r
             SET r.spectatorLastName = :anon,
                 r.spectatorFirstName = :anon,
                 r.spectatorCity = :anon,
                 r.spectatorPhone = :anon,
                 r.spectatorEmail = :anonEmail,
                 r.spectatorComment = NULL
             WHERE r.representation IN (
                 SELECT rep.id FROM App\Entity\Representation rep WHERE rep.datetime < :date
             )
             AND r.spectatorEmail != :anonEmail'
        )
            ->setParameter('anon', 'Anonymisé')
            ->setParameter('anonEmail', 'anonymise@rgpd.local')
            ->setParameter('date', $date)
            ->execute();

        $io->success(sprintf('%d réservation(s) anonymisée(s) (représentations de plus de %d mois).', $count, $months));

        return Command::SUCCESS;
    }
}
