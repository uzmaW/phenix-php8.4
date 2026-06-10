<?php

namespace Phoenix\Core;

final class ServiceLocator
{
    private static ?Container $container = null;

    public static function set(Container $c): void
    {
        self::$container = $c;
    }

    public static function get(string $id): mixed
    {
        if (!self::$container) {
            throw new \RuntimeException('Container not initialized');
        }

        return self::$container->get($id);
    }

    public static function app(): Container
    {
        if (!self::$container) {
            throw new \RuntimeException('Container not initialized');
        }

        return self::$container;
    }
}
