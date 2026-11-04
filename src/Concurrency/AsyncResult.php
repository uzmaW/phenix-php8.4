<?php
declare(strict_types=1);

namespace Phoenix\Concurrency;

use Phoenix\Core\Result;

/**
 * AsyncResult/Future pattern for asynchronous computation.
 *
 * Futures resolve lazily and memoized: a deferred task runs once (on first
 * wait/get/then evaluation) and its outcome is cached. Chained futures settle
 * pull-style, so composition works without an event loop. Every future can be
 * converted to the framework's Phoenix\Core\Result monad via to_result().
 *
 * Inspired by Rust-like PHP framework's AsyncResult
 */
final class AsyncResult
{
    public const PENDING = 'pending';
    public const RESOLVED = 'resolved';
    public const REJECTED = 'rejected';

    private string $status = self::PENDING;
    private mixed $value = null;
    private ?\Throwable $error = null;
    /** @var callable|null */
    private $task = null;
    private ?self $parent = null;
    /** @var callable|null */
    private $on_resolved = null;
    /** @var callable|null */
    private $on_rejected = null;
    private bool $evaluated = false;

    private function __construct() {}

    /** Create an already successful future */
    public static function ok(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $future = new self();
        $future->settle_ok($value);

        return $future;
    }

    /** Create an already failed future */
    public static function err(\Throwable $error): self
    {
        $future = new self();
        $future->settle_err($error);

        return $future;
    }

    /** Create a lazy future; the task runs once, on first evaluation */
    public static function defer(callable $task): self
    {
        $future = new self();
        $future->task = $task;

        return $future;
    }

    /** Wrap an already resolved Phoenix\Core\Result */
    public static function from_result(Result $result): self
    {
        if ($result->isOk()) {
            return self::ok($result->unwrap());
        }

        $payload = $result->unwrapErr();

        return self::err(match (true) {
            $payload instanceof \Throwable => $payload,
            $payload instanceof \Stringable => new \RuntimeException($payload->__toString()),
            is_string($payload) => new \RuntimeException($payload),
            default => new \RuntimeException('Operation failed: ' . json_encode($payload)),
        });
    }

    /** All futures must resolve; fails with the first error encountered */
    public static function all(array $futures): self
    {
        return self::defer(function () use ($futures): array {
            $values = [];
            foreach ($futures as $key => $future) {
                if (!$future instanceof self) {
                    throw new \InvalidArgumentException('AsyncResult::all() expects an array of AsyncResult');
                }
                $values[$key] = $future->get();
            }

            return $values;
        });
    }

    /** First future that settles wins (futures settle synchronously here) */
    public static function race(array $futures): self
    {
        return self::defer(function () use ($futures): mixed {
            foreach ($futures as $future) {
                if (!$future instanceof self) {
                    throw new \InvalidArgumentException('AsyncResult::race() expects an array of AsyncResult');
                }
                if ($future->wait()) {
                    return $future->get();
                }
            }

            throw new \RuntimeException('No future resolved in race()');
        });
    }

    /**
     * Evaluate the future (idempotent, memoized).
     * Returns true when the future resolved with a value.
     */
    public function wait(float $timeout = -1): bool
    {
        $this->evaluate($timeout);

        return $this->status === self::RESOLVED;
    }

    /** Await with an explicit timeout budget (applies to nested futures) */
    public function wait_for(float $timeout = -1): self
    {
        $this->evaluate($timeout);

        return $this;
    }

    /**
     * Resolve and return the value.
     * @throws \RuntimeException when the future is rejected
     */
    public function get(): mixed
    {
        if (!$this->wait()) {
            throw new \RuntimeException(
                $this->error?->getMessage() ?? 'Future was rejected',
                0,
                $this->error,
            );
        }

        return $this->value;
    }

    /** Resolve and return the value, or a default when rejected */
    public function get_value_or(mixed $default): mixed
    {
        return $this->wait() ? $this->value : $default;
    }

    /** Error carried by a rejected future (null while pending/ok) */
    public function get_error(): ?\Throwable
    {
        $this->evaluate();

        return $this->error;
    }

    /** Chain a success continuation, returns a new future */
    public function then(callable $on_resolved, ?callable $on_rejected = null): self
    {
        $child = new self();
        $child->parent = $this;
        $child->on_resolved = $on_resolved;
        $child->on_rejected = $on_rejected;

        return $child;
    }

    /** Chain an error recovery continuation, returns a new future */
    public function catch_(callable $on_rejected): self
    {
        $child = new self();
        $child->parent = $this;
        $child->on_rejected = $on_rejected;

        return $child;
    }

    /** Side-effect continuation that preserves value/error, returns a new future */
    public function finally_(callable $callback): self
    {
        return $this->then(
            function (mixed $value) use ($callback) {
                $callback($value);

                return $value;
            },
            function (\Throwable $error) use ($callback) {
                $callback($error);

                throw $error;
            },
        );
    }

    public function is_pending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function is_resolved(): bool
    {
        $this->evaluate();

        return $this->status === self::RESOLVED;
    }

    public function is_rejected(): bool
    {
        $this->evaluate();

        return $this->status === self::REJECTED;
    }

    public function is_ok(): bool
    {
        return $this->is_resolved();
    }

    public function is_err(): bool
    {
        return $this->is_rejected();
    }

    /** Interop: convert to the framework Result monad */
    public function to_result(): Result
    {
        return $this->wait()
            ? Result::ok($this->value)
            : Result::err($this->error ?? new \RuntimeException('Future was rejected'));
    }

    private function evaluate(float $timeout = -1): void
    {
        if ($this->evaluated) {
            return;
        }

        // Mark first to avoid cycles in self-referencing chains
        $this->evaluated = true;

        if ($this->task !== null) {
            $task = $this->task;
            $this->task = null;

            try {
                $value = $task();
                $value instanceof self
                    ? $this->adopt($value, $timeout)
                    : $this->settle_ok($value);
            } catch (\Throwable $error) {
                $this->settle_err($error);
            }

            return;
        }

        if ($this->parent !== null) {
            $parent = $this->parent;
            $parent->wait($timeout);

            if ($parent->status === self::RESOLVED) {
                $this->continue_ok($parent->value, $timeout);

                return;
            }

            $this->continue_err($parent->error ?? new \RuntimeException('Parent future rejected'), $timeout);
        }
    }

    /** Task returned another future: settle this one from its outcome */
    private function adopt(self $future, float $timeout): void
    {
        $future->wait($timeout);

        if ($future->status === self::RESOLVED) {
            $this->settle_ok($future->value);

            return;
        }

        $this->settle_err($future->error ?? new \RuntimeException('Nested future rejected'));
    }

    private function continue_ok(mixed $value, float $timeout): void
    {
        if ($this->on_resolved === null) {
            $this->settle_ok($value);

            return;
        }

        try {
            $result = ($this->on_resolved)($value);
            $result instanceof self
                ? $this->adopt($result, $timeout)
                : $this->settle_ok($result);
        } catch (\Throwable $error) {
            $this->settle_err($error);
        }
    }

    private function continue_err(\Throwable $error, float $timeout): void
    {
        if ($this->on_rejected === null) {
            $this->settle_err($error);

            return;
        }

        try {
            $result = ($this->on_rejected)($error);
            $result instanceof self
                ? $this->adopt($result, $timeout)
                : $this->settle_ok($result);
        } catch (\Throwable $failure) {
            $this->settle_err($failure);
        }
    }

    private function settle_ok(mixed $value): void
    {
        $this->value = $value;
        $this->status = self::RESOLVED;
        $this->evaluated = true;
    }

    private function settle_err(\Throwable $error): void
    {
        $this->error = $error;
        $this->status = self::REJECTED;
        $this->evaluated = true;
    }
}
