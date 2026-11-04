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

// Admin core routes (protected)
$router->get('/admin', [App\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/dashboard', [App\Controllers\Admin\DashboardController::class, 'index']);
$router->get('/admin/analytics', [App\Controllers\Admin\DashboardController::class, 'analytics']);

// Admin user management
$router->get('/admin/users', [App\Controllers\Admin\UserController::class, 'index']);
$router->get('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'show']);
$router->post('/admin/users', [App\Controllers\Admin\UserController::class, 'create']);
$router->post('/admin/users/update', [App\Controllers\Admin\UserController::class, 'update']);
$router->post('/admin/users/avatar', [App\Controllers\Admin\UserController::class, 'uploadAvatar']);
$router->post('/admin/users/avatar/delete', [App\Controllers\Admin\UserController::class, 'deleteAvatar']);
$router->post('/admin/users/delete', [App\Controllers\Admin\UserController::class, 'delete']);

// Admin payments – Stripe
$router->get('/admin/payments', [App\Controllers\Admin\PaymentController::class, 'index']);
$router->get('/admin/payments/stripe/checkout', [App\Controllers\Admin\PaymentController::class, 'stripeCheckout']);
$router->post('/admin/payments/stripe/checkout', [App\Controllers\Admin\PaymentController::class, 'stripeCheckout']);
$router->get('/admin/payments/stripe/success', [App\Controllers\Admin\PaymentController::class, 'stripeSuccess']);
$router->get('/admin/payments/stripe/cancel', [App\Controllers\Admin\PaymentController::class, 'stripeCancel']);
$router->post('/admin/payments/stripe/webhook', [App\Controllers\Admin\PaymentController::class, 'stripeWebhook']);

// Admin payments – PayPal
$router->get('/admin/payments/paypal/checkout', [App\Controllers\Admin\PaymentController::class, 'paypalCheckout']);
$router->get('/admin/payments/paypal/success', [App\Controllers\Admin\PaymentController::class, 'paypalSuccess']);
$router->get('/admin/payments/paypal/cancel', [App\Controllers\Admin\PaymentController::class, 'paypalCancel']);
