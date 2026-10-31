<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

use Phoenix\Core\Result;
use Phoenix\Error\BusinessLogicError;

/**
 * Base state machine implementation with transition table, guard conditions
 * and validation caching.
 */
abstract class BaseStateMachine implements StateMachine
{
    protected string $current_state;
    protected array $states = [];
    protected array $transitions = [];
    protected array $state_objects = [];

    protected array $metrics = [
        'transitions_attempted' => 0,
        'transitions_successful' => 0,
        'validation_checks' => 0,
        'cached_validations' => 0,
    ];

    private array $validation_cache = [];

    /**
     * Context-sensitive guard conditions must be evaluated fresh on every
     * check, so only the static structure (states registered, transition table
     * entry present) is cached.
     */
    public function __construct(protected string $initial_state, protected object $context)
    {
        $this->initialize_states();
        $this->initialize_transitions();
        $this->current_state = $initial_state;
    }

    abstract protected function initialize_states(): void;

    abstract protected function initialize_transitions(): void;

    protected function add_state(State $state): void
    {
        $this->states[$state->get_name()] = $state;
        $this->state_objects[$state->get_name()] = $state;
    }

    protected function add_transition(StateTransition $transition): void
    {
        $this->transitions[$transition->from_state][] = $transition;
        $this->validation_cache = [];
    }

    protected function get_state_object(string $state_name): ?State
    {
        return $this->state_objects[$state_name] ?? null;
    }

    public function get_metrics(): array
    {
        return $this->metrics;
    }

    public function reset_metrics(): void
    {
        $this->metrics = [
            'transitions_attempted' => 0,
            'transitions_successful' => 0,
            'validation_checks' => 0,
            'cached_validations' => 0,
        ];
        $this->validation_cache = [];
    }

    public function get_current_state(): string
    {
        return $this->current_state;
    }

    public function get_all_states(): array
    {
        return array_keys($this->states);
    }

    public function get_allowed_transitions(): array
    {
        return array_map(
            static fn (StateTransition $transition): string => $transition->to_state,
            $this->transitions[$this->current_state] ?? [],
        );
    }

    public function can_transition_to(string $state): bool
    {
        return $this->can_transition_to_with_context($state, $this->context);
    }

    public function can_transition_to_with_context(string $state, mixed $context): bool
    {
        $this->metrics['validation_checks']++;

        if ($state === $this->current_state) {
            return false;
        }

        // Structural check: target registered and a table entry exists
        $cache_key = $this->current_state . '->' . $state;
        if (array_key_exists($cache_key, $this->validation_cache)) {
            $this->metrics['cached_validations']++;
            if (!$this->validation_cache[$cache_key]) {
                return false;
            }
        } else {
            $structure_ok = in_array($state, $this->get_all_states(), true)
                && isset($this->transitions[$this->current_state]);
            $this->validation_cache[$cache_key] = $structure_ok;
            if (!$structure_ok) {
                return false;
            }
        }

        // A source state may further restrict the transition table
        $source_obj = $this->get_state_object($this->current_state);
        if ($source_obj !== null && !$source_obj->can_transition_to($state)) {
            return false;
        }

        // Guard conditions read live context, so they are never cached
        foreach ($this->transitions[$this->current_state] as $transition) {
            if ($transition->to_state === $state && $transition->can_transition($context)) {
                return true;
            }
        }

        return false;
    }

    public function transition_to(string $state): Result
    {
        return $this->transition_to_with_context($state, $this->context);
    }

    public function transition_to_with_context(string $state, mixed $context): Result
    {
        $this->metrics['transitions_attempted']++;

        if (!$this->can_transition_to_with_context($state, $context)) {
            return Result::err(new BusinessLogicError(
                "Cannot transition from {$this->current_state} to $state",
                'invalid_transition',
            ));
        }

        $previous_state = $this->current_state;

        // Exit current state
        $current_state_obj = $this->get_state_object($previous_state);
        if ($current_state_obj !== null) {
            $current_state_obj->on_exit($this->context);
        }

        // Execute the action of the edge whose guard actually passed
        foreach ($this->transitions[$previous_state] as $transition) {
            if ($transition->to_state === $state && $transition->can_transition($context)) {
                $transition->execute($context);
                break;
            }
        }

        $this->current_state = $state;

        // Enter new state
        $new_state_obj = $this->get_state_object($state);
        if ($new_state_obj !== null) {
            $new_state_obj->on_enter($this->context);
        }

        $this->metrics['transitions_successful']++;

        return Result::ok($state);
    }
}
