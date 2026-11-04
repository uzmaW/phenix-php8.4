<?php
declare(strict_types=1);
namespace Phoenix\Support;

use Phoenix\Core\Result;

/**
 * Laravel-inspired Pipeline with Result integration.
 *
 * Basic usage:
 *   Pipeline::send($payload)
 *       ->through($stage1, $stage2)
 *       ->thenReturn();
 *
 * Each stage receives ($payload, $next) and must either call $next($payload)
 * to continue the chain or return a modified value directly.
 *
 * Alternatively, stages that only transform the value (no $next call) work
 * fine with thenReturn() / then() because the loop simply passes the return
 * value forward.
 */
final class Pipeline
{
    private mixed $payload;

    /** @var list<callable|string> */
    private array $stages = [];

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function send(mixed $payload): self
    {
        $p          = new self();
        $p->payload = $payload;
        return $p;
    }

    // -------------------------------------------------------------------------
    // Stage registration
    // -------------------------------------------------------------------------

    /**
     * Add one or more stages.
     * A stage may be any callable, or a class name whose instances are
     * invocable (__invoke).
     */
    public function through(callable|string ...$stages): self
    {
        $this->stages = array_merge($this->stages, $stages);
        return $this;
    }

    /** Alias for through(). */
    public function pipe(callable|string ...$stages): self
    {
        return $this->through(...$stages);
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Execute all stages sequentially and return the final payload.
     *
     * Stages are called as  $stage($payload, fn($p) => $p).
     * If a stage calls $next($p), the value returned by $next is returned to
     * the caller; effectively the stage can inspect or modify $payload both
     * before and after the rest of the chain.
     *
     * For simple transform-only stages that ignore $next:
     *   fn($payload, $next) => strtoupper($payload)
     * works fine — the return value is used as the next payload.
     */
    public function thenReturn(): mixed
    {
        $payload = $this->payload;

        foreach ($this->stages as $stage) {
            if (is_string($stage)) {
                $stage = new $stage();
            }
            $payload = $stage($payload, fn($p) => $p);
        }

        return $payload;
    }

    /**
     * Execute all stages and wrap the result in a Result monad.
     * Any exception thrown by a stage is caught and returned as Result::err().
     */
    public function thenResult(): Result
    {
        return Result::try(fn() => $this->thenReturn());
    }

    /**
     * Execute all stages then pass the final payload to $destination.
     */
    public function then(callable $destination): mixed
    {
        $payload = $this->payload;

        foreach ($this->stages as $stage) {
            if (is_string($stage)) {
                $stage = new $stage();
            }
            $payload = $stage($payload, fn($p) => $p);
        }

        return $destination($payload);
    }

    // -------------------------------------------------------------------------
    // Middleware onion builder
    // -------------------------------------------------------------------------

    /**
     * Build a classic onion/middleware pipeline where each stage wraps the next.
     *
     * Returns a callable that, when invoked with the payload, runs the entire
     * pipeline with $core as the innermost handler.
     *
     * Each stage receives ($payload, $next) and MUST call $next($payload) to
     * continue, or return without calling it to short-circuit.
     *
     * Example:
     *   $handle = Pipeline::send($req)
     *       ->through(AuthMiddleware::class, LogMiddleware::class)
     *       ->build(fn($req) => new Response(200));
     *   $response = $handle($req);
     */
    public function build(callable $core): callable
    {
        return array_reduce(
            array_reverse($this->stages),
            static function (callable $next, callable|string $stage): callable {
                if (is_string($stage)) {
                    $stage = new $stage();
                }
                return fn($payload) => $stage($payload, $next);
            },
            $core,
        );
    }
}
