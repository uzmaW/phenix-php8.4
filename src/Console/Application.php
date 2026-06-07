<?php
namespace Phoenix\Console;

class Application
{
    private array $commands = [];
    private string $version = '11.0.0';

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    private function registerDefaultCommands(): void
    {
        $this->register(new Commands\InfoCommand());
        $this->register(new Commands\ServeCommand());
        $this->register(new Commands\RouteListCommand());
        $this->register(new Commands\MakeControllerCommand());
        $this->register(new Commands\MakeAgentCommand());
        $this->register(new Commands\CacheClearCommand());
    }

    public function register(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function run(): int
    {
        global $argv;
        $args = $argv ?? [];
        $commandName = $args[1] ?? 'info';

        if (!isset($this->commands[$commandName])) {
            fwrite(STDERR, "Unknown command: $commandName\n");
            $this->showHelp();
            return 1;
        }

        return $this->commands[$commandName]->execute(array_slice($args, 2));
    }

    private function showHelp(): void
    {
        echo "Phoenix Framework v{$this->version}\n\n";
        echo "Available commands:\n";
        foreach ($this->commands as $name => $cmd) {
            echo sprintf("  %-20s %s\n", $name, $cmd->getDescription());
        }
    }
}
