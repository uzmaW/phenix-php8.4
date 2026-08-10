<?php

namespace App\Controllers\Admin;

use Phoenix\Database\Connection;
use Phoenix\View\Factory;

class UserController
{
    private string $uploadPath = 'storage/uploads/avatars';

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
        header('Location: /admin/users/' . $id);
        exit;
    }

    public function uploadAvatar(): string
    {
        $id = $_POST['id'] ?? 0;

        if (!$id || empty($_FILES['avatar']['tmp_name'])) {
            $_SESSION['admin_error'] = 'No file uploaded.';
            header('Location: /admin/users/' . $id);
            exit;
        }

        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $_SESSION['admin_error'] = 'Only JPG, PNG, GIF, and WebP images are allowed.';
            header('Location: /admin/users/' . $id);
            exit;
        }

        if ($file['size'] > $maxSize) {
            $_SESSION['admin_error'] = 'Image must be less than 5MB.';
            header('Location: /admin/users/' . $id);
            exit;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $id . '_' . time() . '.' . strtolower($ext);
        $uploadDir = dirname(__DIR__, 2) . '/' . $this->uploadPath;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $_SESSION['admin_error'] = 'Failed to upload file.';
            header('Location: /admin/users/' . $id);
            exit;
        }

        // Delete old avatar
        $stmt = Connection::get()->prepare('SELECT avatar FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $oldAvatar = $stmt->fetchColumn();
        if ($oldAvatar) {
            $oldPath = dirname(__DIR__, 2) . '/' . $this->uploadPath . '/' . $oldAvatar;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $stmt = Connection::get()->prepare('UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$filename, $id]);

        // Update session if uploading own avatar
        if (isset($_SESSION['admin_user_id']) && $_SESSION['admin_user_id'] == $id) {
            $_SESSION['admin_avatar'] = $filename;
        }

        $_SESSION['admin_success'] = 'Avatar uploaded successfully.';
        header('Location: /admin/users/' . $id);
        exit;
    }

    public function deleteAvatar(): string
    {
        $id = $_POST['id'] ?? 0;

        if ($id) {
            $stmt = Connection::get()->prepare('SELECT avatar FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $avatar = $stmt->fetchColumn();

            if ($avatar) {
                $path = dirname(__DIR__, 2) . '/' . $this->uploadPath . '/' . $avatar;
                if (file_exists($path)) {
                    unlink($path);
                }

                $stmt = Connection::get()->prepare('UPDATE users SET avatar = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$id]);

                if (isset($_SESSION['admin_user_id']) && $_SESSION['admin_user_id'] == $id) {
                    $_SESSION['admin_avatar'] = null;
                }

                $_SESSION['admin_success'] = 'Avatar removed.';
            }
        }

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function delete(): string
    {
        $id = $_POST['id'] ?? 0;

        if ($id) {
            // Delete avatar file
            $stmt = Connection::get()->prepare('SELECT avatar FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $avatar = $stmt->fetchColumn();
            if ($avatar) {
                $path = dirname(__DIR__, 2) . '/' . $this->uploadPath . '/' . $avatar;
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $stmt = Connection::get()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['admin_success'] = "User deleted successfully.";
        }

        header('Location: /admin/users');
        exit;
    }

    public function getAvatarPath(?string $avatar): string
    {
        if ($avatar) {
            return '/' . $this->uploadPath . '/' . $avatar;
        }
        return '';
    }
}
