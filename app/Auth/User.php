<?php

namespace App\Auth;

use Phoenix\Core\Result;
use Phoenix\Core\Traits\Stateful;
use App\Auth\States\UserState;
use App\Services\AuthService;

final class User
{
    use Stateful;

    public function __construct(
        public readonly int $id,
        public string $name,
        public string $email
    ) {
        $this->state = UserState::Guest;
    }

    protected function allowedStates(): array
    {
        return [
            UserState::class,
        ];
    }

    public function login(string $password): Result
    {
        return match ($this->state) {
            UserState::Guest => Result::ok(null)
                ->flatMap(function () use ($password) {
                    $this->transition(UserState::LoggingIn);
                    return AuthService::verify($this, $password);
                })
                ->map(fn() => $this->transition(UserState::Authenticated)),
            UserState::Authenticated => Result::ok($this),
            UserState::Banned => Result::err("User is banned"),
            default => Result::err("Invalid state"),
        };
    }
}
