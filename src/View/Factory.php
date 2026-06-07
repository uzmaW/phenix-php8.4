<?php

namespace Phoenix\View;

final class Factory
{
    private static string $viewsPath = '';
    private static string $cachePath = '';

    public static function init(string $viewsPath, string $cachePath): void
    {
        self::$viewsPath = $viewsPath;
        self::$cachePath = $cachePath;
    }

    public static function make(string $view, array $data = []): View
    {
        return new View($view, $data);
    }

    public static function path(): string
    {
        return self::$viewsPath ?: dirname(__DIR__, 2) . '/views';
    }

    public static function cachePath(): string
    {
        return self::$cachePath ?: dirname(__DIR__, 2) . '/storage/views';
    }
}
