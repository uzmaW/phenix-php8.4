<?php

session_start();

require __DIR__ . '/../vendor/autoload.php';

use Phoenix\Core\{Container, ServiceLocator, RequestLifecycle};
use Phoenix\Database\{Connection, DatabaseManager};
use Phoenix\View\Factory;
use App\Repositories\UserRepository;
use App\Services\AuthService;

// Bootstrap
$container = new Container();
ServiceLocator::set($container);

// Register cleanup for static caches
RequestLifecycle::register();

// Database setup - file-based SQLite (persists across requests)
$dbManager = DatabaseManager::getInstance();
$dbManager->configureConnection();
$ran = $dbManager->runMigrations();

if (!empty($ran)) {
    // Seed on first run
    $seeder = new \Database\Seeders\UserSeeder();
    $seeder->run();
}

// Register repositories
$container->set(UserRepository::class, fn() => new UserRepository());

// Test mode for demo
AuthService::setTestMode(true);

// Initialize views
Factory::init(
    dirname(__DIR__) . '/views',
    dirname(__DIR__) . '/storage/views'
);

// Route to controller
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require dirname(__DIR__) . '/app/routes.php';

$router->dispatch($uri, $method);
