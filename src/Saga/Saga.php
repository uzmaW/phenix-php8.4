<?php
declare(strict_types=1);
namespace Phoenix\Saga;

use Phoenix\Core\Result;

class SagaStep
{
    public function __construct(
        public readonly string $name,
        public readonly \Closure $action,
        public readonly \Closure $compensation,
        public readonly ?string $description = null,
    ) {}
}

class SagaAuditEntry
{
    public function __construct(
        public readonly string $step,
        public readonly string $type,      // 'execute'|'compensate'
        public readonly string $status,    // 'ok'|'err'
        public readonly float $timestamp,
        public readonly mixed $result = null,
        public readonly ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'step'      => $this->step,
            'type'      => $this->type,
            'status'    => $this->status,
            'timestamp' => $this->timestamp,
            'error'     => $this->error,
        ];
    }
}

final class Saga
{
    private array $steps = [];
    private array $auditLog = [];
    private bool $executed = false;
    private bool $dryRun = false;

    /**
     * Add a named step with an action and a compensation callable.
     * Callables are stored as Closures for type-safety; pass any callable
     * via \Closure::fromCallable() if needed.
     */
    public function addStep(
        string $name,
        callable $action,
        callable $compensation,
        ?string $description = null,
    ): self {
        $this->steps[] = new SagaStep(
            $name,
            \Closure::fromCallable($action),
            \Closure::fromCallable($compensation),
            $description,
        );
        return $this;
    }

    /** Enable or disable dry-run mode (simulate without executing side effects). */
    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;
        return $this;
    }

    /** Return the full audit log (populated after execute()). */
    public function getAuditLog(): array
    {
        return $this->auditLog;
    }

    /** Returns true only when execute() completed without any compensation. */
    public function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * Execute all steps in order.
     *
     * On success  → Result::ok(['results' => [...], 'audit' => [...]])
     * On failure  → Result::err(['original_error' => ..., 'compensation_errors' => [...], 'audit' => [...]])
     *               Compensation runs in reverse order for all previously completed steps.
     */
    public function execute(): Result
    {
        $results        = [];
        $completedSteps = [];
        $this->auditLog = [];
        $this->executed = false;

        foreach ($this->steps as $step) {
            $ts = microtime(true);

            try {
                if ($this->dryRun) {
                    $this->auditLog[] = new SagaAuditEntry($step->name, 'execute', 'ok', $ts, null);
                    $results[$step->name] = null;
                    $completedSteps[] = $step;
                    continue;
                }

                $raw    = ($step->action)();
                $result = $raw instanceof Result ? $raw : Result::ok($raw);

                if ($result->isErr()) {
                    $this->auditLog[] = new SagaAuditEntry(
                        $step->name,
                        'execute',
                        'err',
                        $ts,
                        null,
                        (string) $result->unwrapErr(),
                    );
                    return $this->compensate($completedSteps, $result);
                }

                $this->auditLog[] = new SagaAuditEntry(
                    $step->name,
                    'execute',
                    'ok',
                    $ts,
                    $result->unwrap(),
                );
                $results[$step->name] = $result->unwrap();
                $completedSteps[]     = $step;

            } catch (\Throwable $e) {
                $this->auditLog[] = new SagaAuditEntry(
                    $step->name,
                    'execute',
                    'err',
                    $ts,
                    null,
                    $e->getMessage(),
                );
                return $this->compensate($completedSteps, Result::err($e->getMessage()));
            }
        }

        $this->executed = true;
        return Result::ok([
            'results' => $results,
            'audit'   => array_map(fn(SagaAuditEntry $e) => $e->toArray(), $this->auditLog),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function compensate(array $completedSteps, Result $originalError): Result
    {
        $compensationErrors = [];

        foreach (array_reverse($completedSteps) as $step) {
            $ts = microtime(true);
            try {
                ($step->compensation)();
                $this->auditLog[] = new SagaAuditEntry($step->name, 'compensate', 'ok', $ts);
            } catch (\Throwable $e) {
                $msg                  = "Compensation failed for {$step->name}: " . $e->getMessage();
                $compensationErrors[] = $msg;
                $this->auditLog[]     = new SagaAuditEntry(
                    $step->name,
                    'compensate',
                    'err',
                    $ts,
                    null,
                    $e->getMessage(),
                );
                error_log($msg);
            }
        }

        return Result::err([
            'original_error'      => $originalError->unwrapErr(),
            'compensation_errors' => $compensationErrors,
            'audit'               => array_map(fn(SagaAuditEntry $e) => $e->toArray(), $this->auditLog),
        ]);
    }
}
