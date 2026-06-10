<?php

use Phoenix\Core\ServiceLocator;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('app')) {
    function app(string $id = null): mixed
    {
        return $id ? ServiceLocator::get($id) : ServiceLocator::app();
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        return \Phoenix\View\Factory::make($view, $data)->render();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('base58_encode')) {
    function base58_encode(string $data): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = 58;
        $bytes = array_values(unpack('C*', $data));
        $num = '0';
        foreach ($bytes as $byte) {
            $num = bcmul($num, '256');
            $num = bcadd($num, (string) $byte);
        }
        $encoded = '';
        while (bccomp($num, '0') > 0) {
            $remainder = bcmod($num, (string) $base);
            $num = bcdiv($num, (string) $base, 0);
            $encoded = $alphabet[(int) $remainder] . $encoded;
        }
        foreach ($bytes as $byte) {
            if ($byte === 0) {
                $encoded = $alphabet[0] . $encoded;
            } else {
                break;
            }
        }
        return $encoded;
    }
}
