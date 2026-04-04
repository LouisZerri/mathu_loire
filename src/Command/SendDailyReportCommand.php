<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ReportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-daily-report',
    description: 'Envoie le rapport journalier par email aux administrateurs et billettistes.',
)]
class SendDailyReportCommand extends Command
{
    public function __construct(
        private ReportService $reportService,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Emails destinataires (par défaut : tous les admins/billettistes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $recipients = $input->getOption('to');

        if (empty($recipients)) {
            $users = $this->userRepository->findAll();
            $recipients = array_map(fn($u) => $u->getEmail(), $users);
        }

        if (empty($recipients)) {
            $io->warning('Aucun destinataire trouvé.');

            return Command::SUCCESS;
        }

        $sent = $this->reportService->sendDailyReport($recipients);

        $io->success(sprintf('Rapport envoyé à %d destinataire(s).', $sent));

        return Command::SUCCESS;
    }
}
