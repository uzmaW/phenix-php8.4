<?php
namespace Phoenix\Console;

abstract class Command
{
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function execute(array $args): int;

    protected function info(string $message): void
    {
        echo "\033[32m$message\033[0m\n";
    }

    protected function warn(string $message): void
    {
        echo "\033[33m$message\033[0m\n";
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, "\033[31m$message\033[0m\n");
    }
}
