<?php

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envoie un email de rappel J-2 aux spectateurs ayant une réservation validée.',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationMailer $mailer,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $targetDate = new \DateTime('+2 days');
        $reservations = $this->reservationRepository->findForReminder($targetDate);

        if (empty($reservations)) {
            $io->info('Aucun rappel à envoyer.');

            return Command::SUCCESS;
        }

        $sent = 0;
        $errors = 0;

        foreach ($reservations as $reservation) {
            try {
                $this->mailer->sendReminder($reservation);
                $reservation->setReminderSentAt(new \DateTimeImmutable());
                $sent++;
            } catch (\Throwable $e) {
                $io->warning(sprintf(
                    'Erreur pour la réservation #%d (%s) : %s',
                    $reservation->getId(),
                    $reservation->getSpectatorEmail(),
                    $e->getMessage(),
                ));
                $errors++;
            }
        }

        $this->em->flush();

        $io->success(sprintf('%d rappel(s) envoyé(s)%s.', $sent, $errors ? sprintf(', %d erreur(s)', $errors) : ''));

        return Command::SUCCESS;
    }
}
