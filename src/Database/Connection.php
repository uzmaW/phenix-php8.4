<?php

namespace Phoenix\Database;

final class Connection
{
    private static ?\PDO $instance = null;

    public static function get(): \PDO
    {
        return self::$instance ??= new \PDO(
            "sqlite::memory:",
            null,
            null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    public static function set(\PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
