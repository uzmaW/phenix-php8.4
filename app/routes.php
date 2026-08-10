<?php

use Phoenix\Http\Router;
use Phoenix\View\Factory;

$router = new Router();

// Public routes
$router->get('/', function () {
    return Factory::make('layouts/app', [
        'title' => 'Home - Phoenix Framework',
        'content' => Factory::make('welcome')->render(),
    ])->render();
});

$router->get('/about', function () {
    return Factory::make('layouts/app', [
        'title' => 'About - Phoenix Framework',
        'content' => Factory::make('about')->render(),
    ])->render();
});

$router->get('/users', [App\Controllers\UserController::class, 'index']);
$router->get('/users/{id}', [App\Controllers\UserController::class, 'show']);
$router->post('/users', [App\Controllers\UserController::class, 'create']);

// Admin auth routes (public)
$router->get('/admin/login', [App\Controllers\Admin\AuthController::class, 'loginForm']);
$router->post('/admin/login', [App\Controllers\Admin\AuthController::class, 'login']);
$router->get('/admin/register', [App\Controllers\Admin\AuthController::class, 'registerForm']);
$router->post('/admin/register', [App\Controllers\Admin\AuthController::class, 'register']);
$router->get('/admin/logout', [App\Controllers\Admin\AuthController::class, 'logout']);

// Admin routes (protected)
$router->get('/admin', [App\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/dashboard', [App\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/users', [App\Controllers\Admin\UserController::class, 'index']);
$router->get('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'show']);
$router->post('/admin/users', [App\Controllers\Admin\UserController::class, 'create']);
$router->post('/admin/users/update', [App\Controllers\Admin\UserController::class, 'update']);
$router->post('/admin/users/avatar', [App\Controllers\Admin\UserController::class, 'uploadAvatar']);
$router->post('/admin/users/avatar/delete', [App\Controllers\Admin\UserController::class, 'deleteAvatar']);
$router->post('/admin/users/delete', [App\Controllers\Admin\UserController::class, 'delete']);
