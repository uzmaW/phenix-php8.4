<?php

session_start();

// Load .env file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

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

// Admin auth middleware
$isAdminRoute = str_starts_with($uri, '/admin');
$isPublicAuthRoute = str_starts_with($uri, '/admin/login') || str_starts_with($uri, '/admin/register') || str_starts_with($uri, '/admin/logout');
if ($isAdminRoute && !$isPublicAuthRoute && empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/login');
    exit;
}

require dirname(__DIR__) . '/app/routes.php';

echo $router->dispatch($uri, $method);
