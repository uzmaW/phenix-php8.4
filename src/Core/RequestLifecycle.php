<?php

namespace Phoenix\Core;

use Phoenix\Database\{Connection, Repository};
use Phoenix\RateLimit\RateLimiter;

class RequestLifecycle
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        register_shutdown_function([self::class, 'cleanup']);
    }

    public static function cleanup(): void
    {
        try {
            RateLimiter::flushMemory();
        } catch (\Throwable) {
        }

        try {
            Repository::clearReflectionCache();
        } catch (\Throwable) {
        }

        try {
            Connection::clearStatementCache();
        } catch (\Throwable) {
        }
    }
}
