<?php

namespace App\Controllers;

use App\Auth\User;
use App\Auth\States\UserState;
use Phoenix\Database\Connection;
use Phoenix\View\Factory;

class UserController
{
    public function index(): string
    {
        $success = $_SESSION['user_created'] ?? null;
        unset($_SESSION['user_created']);

        $stmt = Connection::get()->prepare("SELECT * FROM users ORDER BY id DESC");
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

        $stmt = Connection::get()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return Factory::make('layouts/app', [
            'title' => ($user['name'] ?? 'User') . ' - Phoenix Framework',
            'content' => Factory::make('users/show', [
                'user' => $user ?: null,
            ])->render(),
        ])->render();
    }

    public function create(): string
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        if ($name && $email) {
            $stmt = Connection::get()->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
            $stmt->execute([$name, $email]);
            $_SESSION['user_created'] = "User \"{$name}\" created successfully.";
        }

        header('Location: /users');
        exit;
    }
}
