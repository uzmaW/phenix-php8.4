<?php

namespace Phoenix\Database;

final class Connection
{
    private static ?\PDO $instance = null;
    private static array $pool = [];
    private static array $statementCache = [];
    private static int $maxPoolSize = 5;
    private static int $cacheSize = 128;
    private static int $connectionTimeout = 5;
    private static string $dsn = '';
    private static ?string $username = null;
    private static ?string $password = null;
    private static array $options = [];

    public static function configure(string $dsn, ?string $username = null, ?string $password = null, array $options = []): void
    {
        self::$dsn = $dsn;
        self::$username = $username;
        self::$password = $password;
        self::$options = $options;
        self::reset();
    }

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
        self::closeAll();
        self::$instance = null;
        self::$statementCache = [];
    }

    public static function prepare(string $sql): \PDOStatement
    {
        $cacheKey = md5($sql);

        if (isset(self::$statementCache[$cacheKey])) {
            $stmt = self::$statementCache[$cacheKey];
            if ($stmt->errorCode() === '00000') {
                $stmt->closeCursor();

                return $stmt;
            }
            unset(self::$statementCache[$cacheKey]);
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

    public static function acquire(): \PDO
    {
        while (!empty(self::$pool)) {
            $entry = array_pop(self::$pool);
            if (self::isConnectionAlive($entry['connection'])) {
                $entry['lastUsed'] = time();

                return $entry['connection'];
            }

            try {
                $entry['connection'] = null;
            } catch (\Throwable) {
            }
        }

        return self::createConnection();
    }

    public static function release(\PDO $connection): void
    {
        if (!self::isConnectionAlive($connection)) {
            $connection = null;

            return;
        }

        try {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        } catch (\Throwable) {
        }

        $idleCount = 0;
        foreach (self::$pool as $entry) {
            if ($entry['connection'] === $connection) {
                return;
            }
            if (time() - $entry['lastUsed'] > 60) {
                $idleCount++;
            }
        }

        if (count(self::$pool) < self::$maxPoolSize) {
            self::$pool[] = [
                'connection' => $connection,
                'lastUsed' => time(),
            ];
        } else {
            $connection = null;
        }
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::get();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    private static function createConnection(): \PDO
    {
        $dsn = self::$dsn ?: 'sqlite::memory:';

        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT => false,
            \PDO::ATTR_TIMEOUT => self::$connectionTimeout,
        ];

        $options = array_merge($defaultOptions, self::$options);

        return new \PDO($dsn, self::$username, self::$password, $options);
    }

    private static function isConnectionAlive(\PDO $connection): bool
    {
        try {
            $connection->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function closeAll(): void
    {
        foreach (self::$pool as $entry) {
            try {
                $entry['connection'] = null;
            } catch (\Throwable) {
            }
        }
        self::$pool = [];
    }

    public static function setMaxPoolSize(int $size): void
    {
        self::$maxPoolSize = $size;
    }

    public static function setCacheSize(int $size): void
    {
        self::$cacheSize = $size;
    }

    public static function setConnectionTimeout(int $seconds): void
    {
        self::$connectionTimeout = $seconds;
    }

    public static function getPoolStats(): array
    {
        $idle = 0;
        $active = 0;
        foreach (self::$pool as $entry) {
            if (time() - $entry['lastUsed'] > 60) {
                $idle++;
            } else {
                $active++;
            }
        }

        return [
            'pool_size' => count(self::$pool),
            'max_pool_size' => self::$maxPoolSize,
            'idle' => $idle,
            'active' => $active,
            'statement_cache_size' => count(self::$statementCache),
        ];
    }

    public static function clearStatementCache(): void
    {
        self::$statementCache = [];
    }
}
