<?php

namespace App\Controllers\Admin;

use Phoenix\Database\Connection;
use Phoenix\View\Factory;

class AuthController
{
    public function loginForm(): string
    {
        if (!empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin');
            exit;
        }

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
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $_SESSION['login_error'] = 'Email and password are required.';
            header('Location: /admin/login');
            exit;
        }

        $stmt = Connection::get()->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_user'] = $user['name'];
            header('Location: /admin');
            exit;
        }

        $_SESSION['login_error'] = 'Invalid email or password.';
        header('Location: /admin/login');
        exit;
    }

    public function registerForm(): string
    {
        if (!empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin');
            exit;
        }

        $error = $_SESSION['register_error'] ?? null;
        unset($_SESSION['register_error']);

        return Factory::make('admin/layouts/auth', [
            'title' => 'Register',
            'content' => Factory::make('admin/auth/register', [
                'error' => $error,
            ])->render(),
        ])->render();
    }

    public function register(): string
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';

        if (!$name || !$email || !$password) {
            $_SESSION['register_error'] = 'All fields are required.';
            header('Location: /admin/register');
            exit;
        }

        if ($password !== $confirm) {
            $_SESSION['register_error'] = 'Passwords do not match.';
            header('Location: /admin/register');
            exit;
        }

        if (strlen($password) < 6) {
            $_SESSION['register_error'] = 'Password must be at least 6 characters.';
            header('Location: /admin/register');
            exit;
        }

        $stmt = Connection::get()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['register_error'] = 'Email already registered.';
            header('Location: /admin/register');
            exit;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Connection::get()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hashed]);

        $_SESSION['login_error'] = 'Registration successful. Please sign in.';
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
