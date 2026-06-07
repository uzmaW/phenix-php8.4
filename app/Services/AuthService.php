<?php

namespace App\Services;

use App\Auth\User;
use Phoenix\Core\Result;

final class AuthService
{
    private static bool $testMode = false;

    public static function setTestMode(bool $mode): void
    {
        self::$testMode = $mode;
    }

    public static function verify(User $user, string $password): Result
    {
        if (self::$testMode) {
            return $password === 'correct-password'
                ? Result::ok(true)
                : Result::err('Wrong password');
        }

        return $password === 'correct-password'
            ? Result::ok(true)
            : Result::err('Wrong password');
    }
}
