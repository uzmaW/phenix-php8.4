<?php
namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class MakeControllerCommand extends Command
{
    public function getName(): string { return 'make:controller'; }
    public function getDescription(): string { return 'Create a new controller class'; }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error("Usage: make:controller <Name>");
            return 1;
        }

        $className = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $path = __DIR__ . '/../../../app/Controllers/' . $className . '.php';

        if (file_exists($path)) {
            $this->error("Controller already exists: $path");
            return 1;
        }

        $stub = "<?php\n\nnamespace App\\Controllers;\n\nclass {$className}\n{\n    public function index(): string\n    {\n        return 'Hello from {$className}';\n    }\n}\n";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->info("Controller created: $path");
        return 0;
    }
}
