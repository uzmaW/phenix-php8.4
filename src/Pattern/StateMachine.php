<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

use Phoenix\Core\MarkerTrait;
use Phoenix\Error\Result;
use Phoenix\Error\Error;

/**
 * State interface
 */
interface State
{
    use MarkerTrait;

    public function get_name(): string;
    public function can_transition_to(string $target_state): bool;
    public function on_enter(object $context): void;
    public function on_exit(object $context): void;
}

/**
 * State transition
 */
class StateTransition
{
    public function __construct(
        public readonly string $from_state,
        public readonly string $to_state,
        public readonly ?callable $condition = null,
        public readonly ?callable $action = null
    ) {}

    public function can_transition(mixed $context = null): bool
    {
        if ($this->condition === null) { return true; }
        return ($this->condition)($context);
    }

    public function execute(mixed $context = null): mixed
    {
        if ($this->action !== null) { return ($this->action)($context); }
        return null;
    }
}

/**
 * State machine interface
 */
interface StateMachine
{
    use MarkerTrait;

    public function get_current_state(): string;
    public function get_all_states(): array;
    public function can_transition_to(string $state): bool;
    public function transition_to(string $state): Result;
    public function transition_to_with_context(string $state, mixed $context): Result;
}