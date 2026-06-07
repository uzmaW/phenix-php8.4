<?php

namespace App\Controllers;

use App\Auth\User;
use App\Auth\States\UserState;

class UserController
{
    public function index(): string
    {
        return "List of all users";
    }

    public function show(): string
    {
        $id = $_GET['id'] ?? 1;
        return "User profile for ID: $id";
    }

    public function create(): string
    {
        $user = new User(
            id: rand(1, 1000),
            name: $_POST['name'] ?? 'New User',
            email: $_POST['email'] ?? 'user@example.com'
        );

        return "User created: {$user->name} ({$user->email})";
    }
}
