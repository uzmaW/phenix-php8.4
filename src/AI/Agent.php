<?php

namespace Phoenix\AI;

use Phoenix\Core\Traits\Observable;

class Agent
{
    use Observable;

    protected string $name;
    protected string $role;
    protected string $goal;
    protected array $memory = [];
    protected bool $running = false;

    public function __construct(
        string $name = 'Agent',
        string $role = 'You are a helpful AI agent',
        string $goal = 'Help users achieve their goals'
    ) {
        $this->name = $name;
        $this->role = $role;
        $this->goal = $goal;
    }

    public function getName(): string { return $this->name; }
    public function getRole(): string { return $this->role; }
    public function getGoal(): string { return $this->goal; }
    public function getMemory(): array { return $this->memory; }

    public function think(string $input): string
    {
        $this->memory[] = ['role' => 'user', 'content' => $input];
        $response = $this->process($input);
        $this->memory[] = ['role' => 'assistant', 'content' => $response];
        $this->notify('thought', ['input' => $input, 'output' => $response]);
        return $response;
    }

    protected function process(string $input): string
    {
        return "Agent {$this->name} processing: {$input}";
    }

    public function start(): void
    {
        $this->running = true;
        $this->notify('started', ['agent' => $this->name]);
    }

    public function stop(): void
    {
        $this->running = false;
        $this->notify('stopped', ['agent' => $this->name]);
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'goal' => $this->goal,
            'running' => $this->running,
            'memory_count' => count($this->memory),
        ];
    }
}
