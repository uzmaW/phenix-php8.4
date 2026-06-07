<?php
namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class RouteListCommand extends Command
{
    public function getName(): string { return 'route:list'; }
    public function getDescription(): string { return 'Display all registered routes'; }

    public function execute(array $args): int
    {
        $this->info("Registered Routes:");
        $this->info(str_repeat('-', 60));

        $routesFile = __DIR__ . '/../../../app/routes.php';
        if (!file_exists($routesFile)) {
            $this->warn("No routes file found at app/routes.php");
            return 0;
        }

        echo sprintf("%-8s %-30s %s\n", "METHOD", "URI", "HANDLER");
        echo str_repeat('-', 60) . "\n";

        require $routesFile;

        return 0;
    }
}
