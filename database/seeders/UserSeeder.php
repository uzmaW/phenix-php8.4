<?php

namespace Database\Seeders;

use Phoenix\Database\Connection;

class UserSeeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com'],
            ['name' => 'Alice Brown', 'email' => 'alice@example.com'],
            ['name' => 'Charlie Davis', 'email' => 'charlie@example.com'],
        ];

        $pdo = Connection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        foreach ($users as $user) {
            try {
                if ($driver === 'sqlite') {
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (name, email) VALUES (?, ?)");
                } else {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, email) VALUES (?, ?)");
                }
                $stmt->execute([$user['name'], $user['email']]);
            } catch (\Throwable $e) {
                // Skip duplicate entries
            }
        }
    }
}
