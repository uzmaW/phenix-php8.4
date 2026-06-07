<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\States\UserState;
use App\Auth\User;
use App\Services\AuthService;
use Phoenix\Core\Result;
use PHPUnit\Framework\TestCase;

final class StateMachineTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        AuthService::setTestMode(true);
        $this->user = new User(1, 'John', 'john@example.com');
    }

    public function test_initial_state_is_guest(): void
    {
        $this->assertSame(UserState::Guest, $this->user->state());
    }

    public function test_can_transition_from_guest_to_logging_in(): void
    {
        $this->user->transition(UserState::LoggingIn);

        $this->assertSame(UserState::LoggingIn, $this->user->state());
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid state transition');

        $fakeState = new class {
            public function __toString()
            {
                return 'Fake';
            }
        };
        $this->user->transition($fakeState);
    }

    public function test_state_change_triggers_observer(): void
    {
        $events = [];

        $this->user->subscribe('stateChanged', function ($payload) use (&$events) {
            $events[] = [
                'from' => $payload['from'],
                'to'   => $payload['to'],
            ];
        });

        $this->user->transition(UserState::LoggingIn);
        $this->user->transition(UserState::Authenticated);

        $this->assertCount(2, $events);
        $this->assertSame(UserState::Guest, $events[0]['from']);
        $this->assertSame(UserState::LoggingIn, $events[0]['to']);
        $this->assertSame(UserState::LoggingIn, $events[1]['from']);
        $this->assertSame(UserState::Authenticated, $events[1]['to']);
    }

    public function test_exhaustive_match_in_login_method(): void
    {
        $result = $this->user->login('correct-password');

        $this->assertTrue($result->isOk());
        $this->assertSame(UserState::Authenticated, $this->user->state());
    }

    public function test_login_from_banned_state_returns_error(): void
    {
        $ref = new \ReflectionProperty($this->user, 'state');
        $ref->setAccessible(true);
        $ref->setValue($this->user, UserState::Banned);

        $result = $this->user->login('anything');

        $this->assertTrue($result->isErr());
        $this->assertSame('User is banned', $result->unwrapOr('User is banned'));
    }

    public function test_cannot_transition_to_non_user_state(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid state transition');

        $fakeState = new \stdClass();
        $this->user->transition($fakeState);
    }

    public function test_result_monad_prevents_unsafe_unwrap(): void
    {
        $ref = new \ReflectionProperty($this->user, 'state');
        $ref->setAccessible(true);
        $ref->setValue($this->user, UserState::Banned);

        $result = $this->user->login('pw');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Called unwrap() on Err');
        $result->unwrap();
    }

    public function test_safe_unwrap_or_works_correctly(): void
    {
        $ref = new \ReflectionProperty($this->user, 'state');
        $ref->setAccessible(true);
        $ref->setValue($this->user, UserState::Banned);

        $result = $this->user->login('pw');

        $this->assertSame('User is banned', $result->unwrapOr('User is banned'));
    }
}
