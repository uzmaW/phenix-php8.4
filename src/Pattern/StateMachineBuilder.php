<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

/**
 * State machine builder
 */
class StateMachineBuilder
{
    private string $name;
    private string $initial_state;
    private object $context;
    private array $states_config = [];
    private array $transitions_config = [];

    public function __construct(string $name) { $this->name = $name; }
    public function initial_state(string $state): self { $this->initial_state = $state; return $this; }
    public function context(object $context): self { $this->context = $context; return $this; }
    public function add_state(string $name, callable $state_factory): self { $this->states_config[$name] = $state_factory; return $this; }
    public function add_transition(string $from, string $to, callable $condition = null, callable $action = null): self { $this->transitions_config[] = new StateTransition($from, $to, $condition, $action); return $this; }
    public function build(): StateMachine {
        $machine = new class($this->name, $this->initial_state, $this->context, $this->states_config, $this->transitions_config) extends BaseStateMachine {
            private array $states_config;
            private array $transitions_config;
            public function __construct(string $name, string $initial_state, object $context, array $states, array $transitions) {
                $this->states_config = $states; $this->transitions_config = $transitions;
                parent::__construct($initial_state, $context);
            }
            protected function initialize_states(): void { foreach ($this->states_config as $name => $state) { $this->add_state($name); } }
            protected function initialize_transitions(): void { foreach ($this->transitions_config as $transition) { $this->add_transition($transition->from_state, $transition->to_state, $transition->condition, $transition->action); } }
            public function add_state(State $state): self { $this->states[$state->get_name()] = $state; $this->state_objects[$state->get_name()] = $state; return $this; }
            public function add_transition(string $from, string $to, callable $condition = null, callable $action = null): self { $this->transitions[] = new StateTransition($from, $to, $condition, $action); return $this; }
        };
        return $machine;
    }
}