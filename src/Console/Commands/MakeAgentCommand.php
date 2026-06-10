<?php

namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class MakeAgentCommand extends Command
{
    public function getName(): string
    {
        return 'make:agent';
    }
    public function getDescription(): string
    {
        return 'Create a new AI agent class';
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: make:agent <Name>');

            return 1;
        }

        $className = $name . 'Agent';
        $path = __DIR__ . '/../../../app/Agents/' . $className . '.php';

        if (file_exists($path)) {
            $this->error("Agent already exists: $path");

            return 1;
        }

        $stub = "<?php\n\nnamespace App\\Agents;\n\nuse Phoenix\\AI\\Agent;\n\nclass {$className} extends Agent\n{\n    public function __construct()\n    {\n        parent::__construct(\n            name: '{$name}',\n            role: 'You are a powerful AI agent',\n            goal: 'Help users achieve their goals'\n        );\n    }\n}\n";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, $stub);
        $this->info("AI Agent created: $className at $path");

        return 0;
    }
}
