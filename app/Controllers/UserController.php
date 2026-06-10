<?php

namespace App\Controllers;

use App\Auth\User;
use Phoenix\Core\ServiceLocator;
use Phoenix\View\Factory;

class UserController
{
    public function index(): string
    {
        $success = $_SESSION['user_created'] ?? null;
        unset($_SESSION['user_created']);

        $repo = ServiceLocator::get(\App\Repositories\UserRepository::class);
        $stmt = \Phoenix\Database\Connection::get()->prepare('SELECT * FROM users ORDER BY id DESC');
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Factory::make('layouts/app', [
            'title' => 'Users - Phoenix Framework',
            'content' => Factory::make('users/index', [
                'users' => $users,
                'success' => $success,
            ])->render(),
        ])->render();
    }

    public function show(): string
    {
        $id = $_GET['id'] ?? 1;

        $repo = ServiceLocator::get(\App\Repositories\UserRepository::class);
        $user = $repo->find((int) $id);

        $userData = $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;

        return Factory::make('layouts/app', [
            'title' => ($user->name ?? 'User') . ' - Phoenix Framework',
            'content' => Factory::make('users/show', [
                'user' => $userData,
            ])->render(),
        ])->render();
    }

    public function create(): string
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        if ($name && $email) {
            $stmt = \Phoenix\Database\Connection::get()->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
            $stmt->execute([$name, $email]);
            $_SESSION['user_created'] = "User \"{$name}\" created successfully.";
        }

        header('Location: /users');
        exit;
    }
}
