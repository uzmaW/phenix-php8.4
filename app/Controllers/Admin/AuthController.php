<?php

namespace App\Controllers\Admin;

use Phoenix\View\Factory;

class AuthController
{
    public function loginForm(): string
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        return Factory::make('admin/layouts/auth', [
            'title' => 'Admin Login',
            'content' => Factory::make('admin/auth/login', [
                'error' => $error,
            ])->render(),
        ])->render();
    }

    public function login(): string
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === 'admin' && $password === 'password') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            header('Location: /admin');
            exit;
        }

        $_SESSION['login_error'] = 'Invalid credentials. Try admin / password.';
        header('Location: /admin/login');
        exit;
    }

    public function logout(): string
    {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }
}
