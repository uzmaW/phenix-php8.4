<?php

namespace Phoenix\Console\Commands;

use Phoenix\Console\Command;

class CacheClearCommand extends Command
{
    public function getName(): string
    {
        return 'cache:clear';
    }
    public function getDescription(): string
    {
        return 'Clear all framework cache';
    }

    public function execute(array $args): int
    {
        $cacheDir = __DIR__ . '/../../../storage/framework/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $this->info('Cache cleared successfully!');

        return 0;
    }
}
