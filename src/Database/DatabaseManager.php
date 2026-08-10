<?php

namespace Phoenix\Database;

use Phoenix\Core\Application;
use Phoenix\Database\Drivers\DriverInterface;
use Phoenix\Database\Drivers\SqliteDriver;
use Phoenix\Database\Drivers\MysqlDriver;
use Phoenix\Database\Drivers\PgsqlDriver;

class DatabaseManager
{
    private static ?self $instance = null;
    private array $config;
    private string $migrationsPath;
    private ?DriverInterface $driver = null;

    private const DRIVERS = [
        'sqlite' => SqliteDriver::class,
        'mysql' => MysqlDriver::class,
        'pgsql' => PgsqlDriver::class,
    ];

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

    public function getDriver(): DriverInterface
    {
        if ($this->driver === null) {
            $default = $this->config['default'] ?? 'sqlite';
            $driverClass = self::DRIVERS[$default] ?? SqliteDriver::class;
            $this->driver = new $driverClass();
        }

        return $this->driver;
    }

    public function configureConnection(): void
    {
        $default = $this->config['default'] ?? 'sqlite';
        $connection = $this->config['connections'][$default] ?? [];
        $driver = $this->getDriver();
        $dsn = $driver->getDsn($connection);

        $username = $connection['username'] ?? null;
        $password = $connection['password'] ?? null;

        Connection::configure($dsn, $username, $password);
    }

    public function getTables(): array
    {
        $driver = $this->getDriver();
        $stmt = Connection::get()->query($driver->getTablesSql());

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getTables());
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

        $table = $this->config['migrations']['table'] ?? 'migrations';
        $stmt = Connection::get()->prepare(
            "SELECT migration FROM {$table} ORDER BY id DESC LIMIT ?"
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

    public function fresh(): void
    {
        $pdo = Connection::get();
        $tables = $this->getTables();

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table} CASCADE");
        }

        $this->runMigrations();
    }

    private function ensureMigrationsTable(): void
    {
        $table = $this->config['migrations']['table'] ?? 'migrations';
        $driver = $this->getDriver();
        Connection::get()->exec($driver->createMigrationsTableSql($table));
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
        $stmt = Connection::get()->prepare("DELETE FROM {$table} WHERE migration = ?");
        $stmt->execute([$name]);
    }
}
