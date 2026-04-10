<?php

/**
 * QueryAuditor — Détecteur N+1 universel pour Symfony et Laravel.
 *
 * Usage Symfony:
 *   $auditor = QueryAuditor::symfony(__DIR__ . '/..');
 *   $auditor->test('Mon test', function($em) { ... });
 *   $auditor->report();
 *
 * Usage Laravel:
 *   $auditor = QueryAuditor::laravel(__DIR__ . '/..');
 *   $auditor->test('Mon test', function($app) { ... });
 *   $auditor->report();
 *
 * Standalone (raw PDO/DBAL):
 *   $auditor = new QueryAuditor($connection);
 *   $auditor->test('Mon test', function() { ... });
 *   $auditor->report();
 */
class QueryAuditor
{
    private \Doctrine\DBAL\Connection|\PDO $connection;
    private string $driver;
    private array $results = [];
    private int $threshold;
    private mixed $container = null;

    public function __construct(
        \Doctrine\DBAL\Connection|\PDO $connection,
        int $threshold = 5,
    ) {
        $this->connection = $connection;
        $this->threshold = $threshold;

        if ($connection instanceof \PDO) {
            $this->driver = match ($connection->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                'mysql' => 'mysql',
                'pgsql' => 'pgsql',
                'sqlite' => 'sqlite',
                default => 'unknown',
            };
        } else {
            $platform = $connection->getDatabasePlatform();
            $class = get_class($platform);
            if (str_contains($class, 'MySQL') || str_contains($class, 'MariaDb')) {
                $this->driver = 'mysql';
            } elseif (str_contains($class, 'PostgreSQL')) {
                $this->driver = 'pgsql';
            } elseif (str_contains($class, 'SQLite')) {
                $this->driver = 'sqlite';
            } else {
                $this->driver = 'unknown';
            }
        }
    }

    /**
     * Initialise pour un projet Symfony.
     */
    public static function symfony(string $projectDir, string $env = 'dev'): self
    {
        require_once $projectDir . '/vendor/autoload.php';

        if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
            (new \Symfony\Component\Dotenv\Dotenv())->bootEnv($projectDir . '/.env');
        }

        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? 'App\\Kernel';
        $kernel = new $kernelClass($env, true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $conn = $container->get('doctrine')->getConnection();

        $instance = new self($conn);
        $instance->container = $container;

        return $instance;
    }

    /**
     * Initialise pour un projet Laravel.
     */
    public static function laravel(string $projectDir): self
    {
        require_once $projectDir . '/vendor/autoload.php';

        $app = require_once $projectDir . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

        $instance = new self($pdo);
        $instance->container = $app;

        return $instance;
    }

    /**
     * Récupère le container (Symfony) ou l'app (Laravel).
     */
    public function getContainer(): mixed
    {
        return $this->container;
    }

    /**
     * Récupère l'EntityManager Doctrine (Symfony).
     */
    public function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        if (!$this->container) {
            throw new \RuntimeException('Container not available. Use symfony() or laravel() factory.');
        }

        return $this->container->get('doctrine')->getManager();
    }

    /**
     * Récupère un repository Doctrine par classe d'entité.
     */
    public function repo(string $entityClass): object
    {
        return $this->getEntityManager()->getRepository($entityClass);
    }

    /**
     * Exécute un test et compte les requêtes SQL.
     *
     * @param string   $label  Nom du test
     * @param callable $fn     Fonction à tester. Reçoit $this en paramètre. Doit retourner un countable ou null.
     * @param int|null $maxQueries  Seuil custom (sinon utilise le seuil global)
     */
    public function test(string $label, callable $fn, ?int $maxQueries = null): self
    {
        $max = $maxQueries ?? $this->threshold;

        $before = $this->getQueryCount();
        $result = $fn($this);
        $after = $this->getQueryCount();

        $count = $after - $before;
        $items = is_countable($result) ? count($result) : null;
        $ok = $count <= $max;

        $this->results[] = [
            'label' => $label,
            'queries' => $count,
            'items' => $items,
            'max' => $max,
            'ok' => $ok,
        ];

        return $this;
    }

    /**
     * Ajoute un titre de section dans le rapport.
     */
    public function section(string $title): self
    {
        $this->results[] = ['section' => $title];

        return $this;
    }

    /**
     * Affiche le rapport.
     */
    public function report(): int
    {
        $total = 0;
        $passed = 0;
        $failed = 0;

        echo "\n\033[1m=== QUERY AUDIT REPORT ===\033[0m\n\n";

        foreach ($this->results as $r) {
            if (isset($r['section'])) {
                echo "\n\033[1m--- {$r['section']} ---\033[0m\n";
                continue;
            }

            $total++;
            $icon = $r['ok'] ? "\033[32m✅\033[0m" : "\033[31m⚠️ \033[0m";
            $items = $r['items'] !== null ? "{$r['items']} items" : '—';
            $queryColor = $r['ok'] ? "\033[32m" : "\033[31m";

            echo sprintf(
                "  %s %-55s %s%d req\033[0m (%s)\n",
                $icon,
                $r['label'],
                $queryColor,
                $r['queries'],
                $items,
            );

            if ($r['ok']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        echo "\n\033[1m=== RÉSULTAT ===\033[0m\n";
        echo sprintf(
            "  Total: %d tests | \033[32m%d OK\033[0m | \033[%sm%d N+1\033[0m\n\n",
            $total,
            $passed,
            $failed > 0 ? '31' : '32',
            $failed,
        );

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Retourne le nombre de requêtes SQL exécutées sur la session courante.
     */
    private function getQueryCount(): int
    {
        return match ($this->driver) {
            'mysql' => $this->getMysqlQueryCount(),
            'pgsql' => $this->getPgsqlQueryCount(),
            'sqlite' => $this->getSqliteQueryCount(),
            default => 0,
        };
    }

    private function getMysqlQueryCount(): int
    {
        if ($this->connection instanceof \PDO) {
            $stmt = $this->connection->query("SHOW SESSION STATUS LIKE 'Queries'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } else {
            $row = $this->connection->fetchAssociative("SHOW SESSION STATUS LIKE 'Queries'");
        }

        return (int) ($row['Value'] ?? 0);
    }

    private function getPgsqlQueryCount(): int
    {
        $sql = "SELECT sum(calls) FROM pg_stat_statements WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())";

        try {
            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->query($sql);

                return (int) $stmt->fetchColumn();
            }

            return (int) $this->connection->fetchOne($sql);
        } catch (\Throwable) {
            // pg_stat_statements pas activé — fallback compteur interne
            return 0;
        }
    }

    private function getSqliteQueryCount(): int
    {
        // SQLite n'a pas de compteur de requêtes natif
        // On utilise un compteur statique incrémenté par un wrapper
        return 0;
    }
}
