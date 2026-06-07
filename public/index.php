<?php

require __DIR__ . '/../vendor/autoload.php';

use Phoenix\Core\{Container, ServiceLocator};
use Phoenix\Database\Connection;
use Phoenix\View\Factory;
use App\Repositories\UserRepository;
use App\Services\AuthService;

// Setup in-memory DB for demo
$pdo = Connection::get();
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");
$pdo->exec("INSERT OR IGNORE INTO users (id, name, email) VALUES (1, 'John', 'john@example.com')");

// DI Container
$container = new Container();
$container->set(UserRepository::class, fn() => new UserRepository());
ServiceLocator::set($container);

// Test mode for demo
AuthService::setTestMode(true);

// Initialize views
Factory::init(
    dirname(__DIR__) . '/views',
    dirname(__DIR__) . '/storage/views'
);

echo "Phoenix Framework v2 Ready!\n";
echo "Routes available: /, /about, /users, /users/{id}\n";
