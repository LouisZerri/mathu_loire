<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Réinitialise la base de données dédiée aux tests Playwright (drop + create + schema + fixtures).
 *
 * Chaque étape est exécutée dans un sous-process pour éviter que la connexion Doctrine
 * pointe vers une base déjà droppée. Doit être lancée avec APP_ENV=test_e2e.
 */
#[AsCommand(name: 'app:e2e:reset-db', description: 'Reset la base de données E2E (test_e2e uniquement).')]
class ResetE2eDatabaseCommand extends Command
{
    public function __construct(
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $env = $_SERVER['APP_ENV'] ?? 'dev';

        if ($env !== 'test_e2e') {
            $io->error(sprintf('Cette commande doit être lancée avec APP_ENV=test_e2e (env actuel : %s).', $env));

            return Command::FAILURE;
        }

        $steps = [
            ['doctrine:database:drop', '--force', '--if-exists'],
            ['doctrine:database:create'],
            ['doctrine:schema:create'],
            ['doctrine:fixtures:load', '--no-interaction'],
        ];

        foreach ($steps as $args) {
            $io->writeln(sprintf('<info>→ %s</info>', $args[0]));
            $process = new Process(['php', 'bin/console', ...$args, '--env=test_e2e'], $this->projectDir);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error(sprintf("Échec de %s :\n%s", $args[0], $process->getErrorOutput() ?: $process->getOutput()));

                return Command::FAILURE;
            }
        }

        $io->success('Base E2E réinitialisée.');

        return Command::SUCCESS;
    }
}
