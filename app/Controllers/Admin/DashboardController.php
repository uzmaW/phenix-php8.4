<?php

namespace App\Controllers\Admin;

use Phoenix\Database\Connection;
use Phoenix\View\Factory;

class DashboardController
{
    public function index(): string
    {
        $pdo = Connection::get();

        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $recentUsers = $pdo->query('SELECT * FROM users ORDER BY id DESC LIMIT 5')->fetchAll(\PDO::FETCH_ASSOC);

        $dbManager = \Phoenix\Database\DatabaseManager::getInstance();
        $totalTables = count($dbManager->getTables());

        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);

        return Factory::make('admin/layouts/admin', [
            'title' => 'Admin Dashboard',
            'content' => Factory::make('admin/dashboard/index', [
                'totalUsers' => $totalUsers,
                'recentUsers' => $recentUsers,
                'totalTables' => $totalTables,
                'memoryUsage' => $memoryUsage,
                'phpVersion' => PHP_VERSION,
            ])->render(),
            'currentPage' => 'dashboard',
        ])->render();
    }
}
