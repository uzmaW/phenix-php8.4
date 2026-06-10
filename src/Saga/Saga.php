<?php

namespace Phoenix\Saga;

final class Saga
{
    private array $steps = [];
    private array $compensations = [];
    private bool $executed = false;

    public function addStep(string $name, callable $action, callable $compensation): self
    {
        $this->steps[] = ['name' => $name, 'action' => $action];
        $this->compensations[] = $compensation;

        return $this;
    }

    public function execute(): array
    {
        $results = [];
        $completedSteps = 0;

        try {
            foreach ($this->steps as $i => $step) {
                $results[] = $step['action']();
                $completedSteps++;
            }
            $this->executed = true;

            return $results;
        } catch (\Throwable $e) {
            for ($i = $completedSteps - 1; $i >= 0; $i--) {
                try {
                    $this->compensations[$i]();
                } catch (\Throwable $compError) {
                    error_log("Compensation failed for step {$i}: " . $compError->getMessage());
                }
            }

            throw $e;
        }
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }
}
