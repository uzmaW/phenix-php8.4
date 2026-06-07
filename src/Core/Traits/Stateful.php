<?php

namespace Phoenix\Core\Traits;

trait Stateful
{
    use Observable;

    private mixed $state;

    final public function transition(mixed $newState): void
    {
        $stateClass = get_class($newState);

        if (!in_array($stateClass, $this->allowedStates(), true)) {
            throw new \LogicException("Invalid state transition to $stateClass");
        }

        $old = $this->state;
        $this->state = $newState;
        $this->notify('stateChanged', ['from' => $old, 'to' => $newState]);
    }

    final public function state(): mixed
    {
        return $this->state;
    }

    abstract protected function allowedStates(): array;
}
