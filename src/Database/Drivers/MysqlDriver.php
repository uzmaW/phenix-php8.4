<?php

namespace Phoenix\Database\Drivers;

final class MysqlDriver implements DriverInterface
{
    public function getDsn(array $config): string
    {
        return "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    }

    public function createMigrationsTableSql(string $table): string
    {
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    }

    public function getUsersTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function getTablesSql(): string
    {
        return 'SHOW TABLES';
    }
}
