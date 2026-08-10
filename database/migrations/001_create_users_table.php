<?php

use Phoenix\Database\Drivers\SqliteDriver;
use Phoenix\Database\Drivers\MysqlDriver;
use Phoenix\Database\Drivers\PgsqlDriver;

return function (\PDO $pdo) {
    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

    $drivers = [
        'sqlite' => new SqliteDriver(),
        'mysql' => new MysqlDriver(),
        'pgsql' => new PgsqlDriver(),
    ];

    $adapter = $drivers[$driver] ?? new SqliteDriver();
    $pdo->exec($adapter->getUsersTableSql());
};
