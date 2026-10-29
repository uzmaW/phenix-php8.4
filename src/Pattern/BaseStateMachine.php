<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

/**
 * Base state machine implementation with performance optimizations
 */
abstract class BaseStateMachine implements StateMachine
{
    use MarkerTrait;

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

    public function get_metrics(): array { return $this->metrics; }
    public function reset_metrics(): void { $this->metrics = ['transitions_attempted'=>0,'transitions_successful'=>0,'validation_checks'=>0,'cached_validations'=>0]; $this->validation_cache = []; }

    public function __construct(protected string $initial_state, protected object $context) {
        $this->initialize_states(); $this->initialize_transitions(); $this->current_state = $initial_state;
    }

    abstract protected function initialize_states(): void;
    abstract protected function initialize_transitions(): void;

    protected function get_state_object(string $state_name): ?State { return $this->state_objects[$state_name] ?? null; }

    public function get_current_state(): string { return $this->current_state; }
    public function get_all_states(): array { return array_keys($this->states); }

    public function can_transition_to(string $state): bool {
        if (!in_array($state, $this->get_all_states(), true)) { return false; }
        $this->metrics['validation_checks']++;
        $state_obj = $this->get_state_object($state);
        if ($state_obj === null) { return false; }
        $condition_met = $state_obj->can_transition_to($state);
        $this->metrics['validation_checks']++;
        return $condition_met;
    }

    public function transition_to(string $state): Result {
        $this->metrics['transitions_attempted']++;
        if (!in_array($state, $this->get_all_states(), true)) { return Result::err(new Error("State '$state' not found")); }
        $state_obj = $this->get_state_object($state);
        if ($state_obj === null) { return Result::err(new Error("State object not found")); }
        $condition_met = $state_obj->can_transition_to($state);
        $this->metrics['validation_checks']++;
        if (!$condition_met) { return Result::err(new Error("Transition condition not met from {$this->current_state} to $state")); }
        $current_state_obj = $this->get_state_object($this->current_state);
        if ($current_state_obj !== null) { $current_state_obj->on_exit($this->context); }
        $state_obj = $this->get_state_object($state);
        if ($state_obj !== null) { $state_obj->on_enter($this->context); }
        $previous_state = $this->current_state;
        $this->current_state = $state;
        $this->metrics['transitions_successful']++;
        return Result::ok($previous_state);
    }

    public function transition_to_with_context(string $state, mixed $context): Result {
        $result = $this->transition_to($state);
        if ($result->is_ok()) { $result->get_value()['context'] = $context; }
        return $result;
    }
}