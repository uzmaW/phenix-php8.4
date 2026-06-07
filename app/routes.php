<?php

use Phoenix\Http\Router;

$router = new Router();

$router->get('/', fn() => 'Phoenix Framework v2 - Welcome!');
$router->get('/about', fn() => 'About Phoenix Framework');
$router->get('/users', [App\Controllers\UserController::class, 'index']);
$router->get('/users/{id}', [App\Controllers\UserController::class, 'show']);
$router->post('/users', [App\Controllers\UserController::class, 'create']);
