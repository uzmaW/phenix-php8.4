<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

use Phoenix\Core\Result;

/**
 * State interface
 */
interface State
{
    public function get_name(): string;
    public function can_transition_to(string $target_state): bool;
    public function on_enter(object $context): void;
    public function on_exit(object $context): void;
}

/**
 * State transition with optional guard condition and side effect
 */
class StateTransition
{
    public function __construct(
        public readonly string $from_state,
        public readonly string $to_state,
        private readonly mixed $condition = null,
        private readonly mixed $action = null,
    ) {}

    public function can_transition(mixed $context = null): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return (bool) ($this->condition)($context);
    }

    public function execute(mixed $context = null): mixed
    {
        if ($this->action !== null) {
            return ($this->action)($context);
        }

        return null;
    }
}

/**
 * State machine interface
 */
interface StateMachine
{
    public function get_current_state(): string;
    public function get_all_states(): array;
    public function can_transition_to(string $state): bool;
    public function transition_to(string $state): Result;
    public function transition_to_with_context(string $state, mixed $context): Result;
}

/**
 * Generic state implementation with explicit allow-list, entry and exit hooks
 */
class GenericState implements State
{
    private array $allowed_transitions = [];
    private array $entry_actions = [];
    private array $exit_actions = [];

    public function __construct(public readonly string $name) {}

    public function get_name(): string
    {
        return $this->name;
    }

    public function can_transition_to(string $target_state): bool
    {
        return $this->allowed_transitions === []
            || in_array($target_state, $this->allowed_transitions, true);
    }

    public function on_enter(object $context): void
    {
        foreach ($this->entry_actions as $action) {
            $action($context);
        }
    }

    public function on_exit(object $context): void
    {
        foreach ($this->exit_actions as $action) {
            $action($context);
        }
    }

    /** Add allowed transition */
    public function allow_transition_to(string $state): self
    {
        $this->allowed_transitions[] = $state;

        return $this;
    }

    /** Add entry action */
    public function on_entry(callable $action): self
    {
        $this->entry_actions[] = $action;

        return $this;
    }

    /** Add exit action */
    public function on_exit_action(callable $action): self
    {
        $this->exit_actions[] = $action;

        return $this;
    }
}
