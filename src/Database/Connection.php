<?php

namespace Phoenix\Database;

final class Connection
{
    private static ?\PDO $instance = null;
    private static array $pool = [];
    private static array $statementCache = [];
    private static int $maxPoolSize = 5;
    private static int $cacheSize = 128;

    public static function get(): \PDO
    {
        return self::$instance ??= self::createConnection();
    }

    public static function set(\PDO $pdo): void
    {
        self::$instance = $pdo;
        self::$statementCache = [];
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$pool = [];
        self::$statementCache = [];
    }

    public static function prepare(string $sql): \PDOStatement
    {
        $cacheKey = md5($sql);

        if (isset(self::$statementCache[$cacheKey])) {
            $stmt = self::$statementCache[$cacheKey];
            $stmt->closeCursor();
            return $stmt;
        }

        $stmt = self::get()->prepare($sql);

        if (count(self::$statementCache) >= self::$cacheSize) {
            array_shift(self::$statementCache);
        }
        self::$statementCache[$cacheKey] = $stmt;

        return $stmt;
    }

    public static function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function beginTransaction(): bool
    {
        return self::get()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::get()->commit();
    }

    public static function rollBack(): bool
    {
        return self::get()->rollBack();
    }

    public static function pool(): ?\PDO
    {
        if (!empty(self::$pool)) {
            return array_pop(self::$pool);
        }
        return self::createConnection();
    }

    public static function release(\PDO $connection): void
    {
        try {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
            $connection->exec('ROLLBACK');
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable) {
        }

        if (count(self::$pool) < self::$maxPoolSize) {
            self::$pool[] = $connection;
        } else {
            $connection = null;
        }
    }

    private static function createConnection(): \PDO
    {
        return new \PDO(
            "sqlite::memory:",
            null,
            null,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
    }

    public static function setMaxPoolSize(int $size): void
    {
        self::$maxPoolSize = $size;
    }

    public static function setCacheSize(int $size): void
    {
        self::$cacheSize = $size;
    }

    public static function clearStatementCache(): void
    {
        self::$statementCache = [];
    }
}
