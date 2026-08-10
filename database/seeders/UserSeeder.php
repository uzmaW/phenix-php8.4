<?php

namespace Database\Seeders;

use Phoenix\Database\Connection;

class UserSeeder
{
    public function run(): void
    {
        $defaultPassword = password_hash('password', PASSWORD_DEFAULT);

        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => $defaultPassword],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => $defaultPassword],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'password' => $defaultPassword],
            ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'password' => $defaultPassword],
            ['name' => 'Charlie Davis', 'email' => 'charlie@example.com', 'password' => $defaultPassword],
        ];

        $pdo = Connection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        foreach ($users as $user) {
            try {
                if ($driver === 'sqlite') {
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (name, email, password) VALUES (?, ?, ?)");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?) ON CONFLICT (email) DO NOTHING");
                }
                $stmt->execute([$user['name'], $user['email'], $user['password']]);
            } catch (\Throwable $e) {
                // Skip duplicate entries
            }
        }
    }
}
