<?php

namespace Phoenix\Core\Traits;

trait Observable
{
    private array $observers = [];

    public function subscribe(string $event, \Closure $listener): void
    {
        $this->observers[$event][] = $listener;
    }

    protected function notify(string $event, mixed $payload = null): void
    {
        foreach ($this->observers[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
