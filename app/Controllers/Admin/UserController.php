<?php

namespace App\Controllers\Admin;

use Phoenix\Database\Connection;
use Phoenix\View\Factory;

class UserController
{
    public function index(): string
    {
        $success = $_SESSION['admin_success'] ?? null;
        $error = $_SESSION['admin_error'] ?? null;
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);

        $stmt = Connection::get()->prepare('SELECT * FROM users ORDER BY id DESC');
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Factory::make('admin/layouts/admin', [
            'title' => 'Manage Users',
            'content' => Factory::make('admin/users/index', [
                'users' => $users,
                'success' => $success,
                'error' => $error,
            ])->render(),
            'currentPage' => 'users',
        ])->render();
    }

    public function show(): string
    {
        $id = $_GET['id'] ?? 0;
        $success = $_SESSION['admin_success'] ?? null;
        $error = $_SESSION['admin_error'] ?? null;
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);

        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['admin_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        return Factory::make('admin/layouts/admin', [
            'title' => $user['name'],
            'content' => Factory::make('admin/users/show', [
                'user' => $user,
                'success' => $success,
                'error' => $error,
            ])->render(),
            'currentPage' => 'users',
        ])->render();
    }

    public function create(): string
    {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $_SESSION['admin_error'] = 'All fields are required.';
            header('Location: /admin/users');
            exit;
        }

        $stmt = Connection::get()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['admin_error'] = 'Email already exists.';
            header('Location: /admin/users');
            exit;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Connection::get()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hashed]);
        $_SESSION['admin_success'] = "User \"{$name}\" created successfully.";

        header('Location: /admin/users');
        exit;
    }

    public function update(): string
    {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$id || !$name || !$email) {
            $_SESSION['admin_error'] = 'Name and email are required.';
            header('Location: /admin/users?id=' . $id);
            exit;
        }

        $stmt = Connection::get()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $_SESSION['admin_error'] = 'Email already taken by another user.';
            header('Location: /admin/users?id=' . $id);
            exit;
        }

        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = Connection::get()->prepare('UPDATE users SET name = ?, email = ?, password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$name, $email, $hashed, $id]);
        } else {
            $stmt = Connection::get()->prepare('UPDATE users SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$name, $email, $id]);
        }

        $_SESSION['admin_success'] = "User updated successfully.";
        header('Location: /admin/users?id=' . $id);
        exit;
    }

    public function delete(): string
    {
        $id = $_POST['id'] ?? 0;

        if ($id) {
            $stmt = Connection::get()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['admin_success'] = "User deleted successfully.";
        }

        header('Location: /admin/users');
        exit;
    }
}
