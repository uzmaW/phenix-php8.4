<?php

namespace Phoenix\Database\Drivers;

interface DriverInterface
{
    public function getDsn(array $config): string;

    public function createMigrationsTableSql(string $table): string;

    public function getUsersTableSql(): string;

    public function getTablesSql(): string;
}
