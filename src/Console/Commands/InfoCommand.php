<?php
namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class InfoCommand extends Command
{
    public function getName(): string { return 'info'; }
    public function getDescription(): string { return 'Show Phoenix Framework info'; }

    public function execute(array $args): int
    {
        $this->info("
    ██████╗ ██╗  ██╗███████╗███╗   ██╗██╗██╗  ██╗
    ██╔══██╗██║  ██║██╔════╝████╗  ██║██║╚██╗██╔╝
    ██████╔╝███████║█████╗  ██╔██╗ ██║██║ ╚███╔╝
    ██╔═══╝ ██╔══██║██╔══╝  ██║╚██╗██║██║ ██╔██╗
    ██║     ██║  ██║███████╗██║ ╚████║██║██╔╝ ██╗
    ╚═╝     ╚═╝  ╚═╝╚══════╝╚═╝  ╚═══╝╚═╝╚═╝  ╚═╝ v11
");
        $this->info("Phoenix Framework v11.0.0 - The Final Evolution");
        $this->warn("Multi-chain • AI Agents • Real-Time • Secure");
        $this->warn("Pure PHP 8.2+ • No external services required for core");
        return 0;
    }
}
