<?php

namespace Phoenix\Database;

use PDO;

final class Transaction
{
    public static function begin(): void
    {
        Connection::get()->beginTransaction();
    }

    public static function commit(): void
    {
        Connection::get()->commit();
    }

    public static function rollback(): void
    {
        Connection::get()->rollBack();
    }

    public static function execute(callable $callback): mixed
    {
        self::begin();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }
}
