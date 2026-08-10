<?php

namespace App\Middleware;

use Phoenix\Middleware\MiddlewareInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        if (empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }

        return $next($request);
    }
}
