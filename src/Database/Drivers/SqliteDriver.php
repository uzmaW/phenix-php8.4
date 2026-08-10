<?php

namespace Phoenix\Database\Drivers;

final class SqliteDriver implements DriverInterface
{
    public function getDsn(array $config): string
    {
        $database = $config['database'] ?? base_path('storage/database.sqlite');
        $dir = dirname($database);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        return "sqlite:{$database}";
    }

    public function createMigrationsTableSql(string $table): string
    {
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
    }

    public function getUsersTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
    }

    public function getTablesSql(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
    }
}
