<?php

namespace Phoenix\Database;

use Phoenix\Core\Application;

class DatabaseManager
{
    private static ?self $instance = null;
    private array $config;
    private string $migrationsPath;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->migrationsPath = $config['migrations']['path'] ?? base_path('database/migrations');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $config = require base_path('config/database.php');
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function configureConnection(): void
    {
        $default = $this->config['default'] ?? 'sqlite';
        $connection = $this->config['connections'][$default] ?? [];

        if ($connection['driver'] === 'sqlite') {
            $database = $connection['database'] ?? base_path('storage/database.sqlite');
            $dir = dirname($database);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            Connection::configure("sqlite:{$database}");
        } elseif ($connection['driver'] === 'mysql') {
            $dsn = "mysql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']};charset={$connection['charset']}";
            Connection::configure($dsn, $connection['username'], $connection['password']);
        } elseif ($connection['driver'] === 'pgsql') {
            $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
            Connection::configure($dsn, $connection['username'], $connection['password']);
        }
    }

    public function getTables(): array
    {
        $pdo = Connection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        } else {
            $stmt = $pdo->query('SHOW TABLES');
        }

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function tableExists(string $table): bool
    {
        $tables = $this->getTables();

        return in_array($table, $tables);
    }

    public function runMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0o755, true);
        }

        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $files = glob($this->migrationsPath . '/*.php');
        $ran = [];

        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $executed)) {
                continue;
            }

            $migration = require $file;
            if (is_callable($migration)) {
                $migration(Connection::get());
            }

            $this->recordMigration($name);
            $ran[] = $name;
        }

        return $ran;
    }

    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTable();

        $stmt = Connection::get()->prepare(
            'SELECT migration FROM ' . $this->config['migrations']['table'] . ' ORDER BY id DESC LIMIT ?',
        );
        $stmt->execute([$steps]);
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $rolledBack = [];

        foreach ($migrations as $name) {
            $file = $this->migrationsPath . '/' . $name . '.php';
            if (file_exists($file)) {
                $migration = require $file;
                if (is_callable($migration) && method_exists($migration, 'down')) {
                    $migration->down(Connection::get());
                }
            }

            $this->removeMigration($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    public function seed(string $seeder): void
    {
        if (class_exists($seeder)) {
            $instance = new $seeder();
            if (method_exists($instance, 'run')) {
                $instance->run();
            }
        }
    }

    public function fresh(): void
    {
        $pdo = Connection::get();
        $tables = $this->getTables();

        $pdo->exec('SET foreign_key_checks = 0');
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $pdo->exec('SET foreign_key_checks = 1');

        $this->runMigrations();
    }

    private function ensureMigrationsTable(): void
    {
        $table = $this->config['migrations']['table'] ?? 'migrations';
        $pdo = Connection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL, executed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        }
    }

    private function getExecutedMigrations(): array
    {
        $table = $this->config['migrations']['table'] ?? 'migrations';
        $stmt = Connection::get()->query("SELECT migration FROM {$table} ORDER BY id");

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function recordMigration(string $name): void
    {
        $table = $this->config['migrations']['table'] ?? 'migrations';
        $stmt = Connection::get()->prepare("INSERT INTO {$table} (migration) VALUES (?)");
        $stmt->execute([$name]);
    }

    private function removeMigration(string $name): void
    {
        $table = $this->config['migrations']['table'] ?? 'migrations';
        $stmt = Connection::get()->prepare("DELETE FROM {$table} WHERE migration = ? LIMIT 1");
        $stmt->execute([$name]);
    }
}
