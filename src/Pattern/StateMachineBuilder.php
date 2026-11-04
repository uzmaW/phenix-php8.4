<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

/**
 * Fluent builder for configured state machines
 */
class StateMachineBuilder
{
    private string $initial_state = '';
    private ?object $context = null;
    /** @var array<string, State> */
    private array $states = [];
    private array $transitions = [];

    public function __construct(private readonly string $name) {}

    /** Ergonomic entry point */
    public static function create(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function with_context(object $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function initial_state(string $state): self
    {
        $this->initial_state = $state;

        return $this;
    }

    /** Register a state; $configure can add allow-lists and entry/exit hooks */
    public function state(string $name, ?callable $configure = null): self
    {
        $state = new GenericState($name);
        if ($configure !== null) {
            $configure($state);
        }
        $this->states[$name] = $state;

        return $this;
    }

    /** Register an existing State implementation */
    public function with_state(State $state): self
    {
        $this->states[$state->get_name()] = $state;

        return $this;
    }

    public function transition(string $from, string $to, ?callable $condition = null, ?callable $action = null): self
    {
        $this->transitions[] = new StateTransition($from, $to, $condition, $action);

        return $this;
    }

    public function build(): StateMachine
    {
        if ($this->context === null) {
            throw new \InvalidArgumentException('Context is required for state machine');
        }

        if ($this->initial_state === '') {
            throw new \InvalidArgumentException('Initial state is required for state machine');
        }

        if (!isset($this->states[$this->initial_state])) {
            throw new \InvalidArgumentException("Initial state '{$this->initial_state}' is not registered");
        }

        return new class($this->initial_state, $this->context, $this->states, $this->transitions) extends BaseStateMachine {
            /** @var array<string, State> */
            private array $states_config;
            private array $transitions_config;

            public function __construct(
                string $initial_state,
                object $context,
                array $states,
                array $transitions,
            ) {
                $this->states_config = $states;
                $this->transitions_config = $transitions;
                parent::__construct($initial_state, $context);
            }

            protected function initialize_states(): void
            {
                foreach ($this->states_config as $state) {
                    $this->add_state($state);
                }
            }

            protected function initialize_transitions(): void
            {
                foreach ($this->transitions_config as $transition) {
                    $this->add_transition($transition);
                }
            }
        };
    }
}

/**
 * State machine utilities
 */
class StateMachineUtils
{
    /** Create builder */
    public static function create(string $name): StateMachineBuilder
    {
        return new StateMachineBuilder($name);
    }

    /** Create simple all-to-all state machine over a list of state names */
    public static function create_simple(string $initial_state, array $states): StateMachine
    {
        $context = new \stdClass();
        $builder = new StateMachineBuilder('simple');
        $builder->with_context($context)->initial_state($initial_state);

        foreach ($states as $state) {
            $builder->state($state);
        }

        foreach ($states as $from) {
            foreach ($states as $to) {
                if ($from !== $to) {
                    $builder->transition($from, $to);
                }
            }
        }

        return $builder->build();
    }
}
