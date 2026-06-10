<?php

namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class ServeCommand extends Command
{
    public function getName(): string
    {
        return 'serve';
    }
    public function getDescription(): string
    {
        return 'Start the built-in PHP development server';
    }

    public function execute(array $args): int
    {
        $host = $args[0] ?? '127.0.0.1';
        $port = $args[1] ?? '8000';

        $this->info("Phoenix Development Server started: http://{$host}:{$port}");
        $this->warn('Press Ctrl+C to stop');

        passthru(sprintf('php -S %s:%d -t public', $host, $port));

        return 0;
    }
}
