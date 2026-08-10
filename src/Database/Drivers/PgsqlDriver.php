<?php

namespace Phoenix\Database\Drivers;

final class PgsqlDriver implements DriverInterface
{
    public function getDsn(array $config): string
    {
        return "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    }

    public function createMigrationsTableSql(string $table): string
    {
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    }

    public function getUsersTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    }

    public function getTablesSql(): string
    {
        return "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename";
    }
}
