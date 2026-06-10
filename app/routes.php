<?php

use Phoenix\Http\Router;
use Phoenix\View\Factory;

$router = new Router();

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
