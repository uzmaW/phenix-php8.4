<?php

declare(strict_types=1);

namespace Phoenix\Core;

/**
 * Rust-inspired Result monad for explicit error handling without exceptions.
 *
 * Every fallible operation returns a Result<T, E> instead of throwing.
 * All combinators are pure and return new Result instances.
 */
final class Result
{
    private function __construct(
        private readonly bool $isOk,
        private readonly mixed $valueOrError,
    ) {}

    // -----------------------------------------------------------------------
    // Constructors
    // -----------------------------------------------------------------------

    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function err(mixed $error): self
    {
        return new self(false, $error);
    }

    /**
     * Wrap a callable in a Result: Ok on success, Err with the Throwable on failure.
     * Analogous to Rust's std::panic::catch_unwind.
     */
    public static function try(callable $fn): self
    {
        try {
            return self::ok($fn());
        } catch (\Throwable $e) {
            return self::err($e);
        }
    }

    // -----------------------------------------------------------------------
    // Inspection
    // -----------------------------------------------------------------------

    public function isOk(): bool
    {
        return $this->isOk;
    }

    public function isErr(): bool
    {
        return !$this->isOk;
    }

    // -----------------------------------------------------------------------
    // Extraction (unsafe & safe)
    // -----------------------------------------------------------------------

    /** @throws \RuntimeException when called on Err */
    public function unwrap(): mixed
    {
        if (!$this->isOk) {
            $msg = $this->valueOrError instanceof \Throwable
                ? $this->valueOrError->getMessage()
                : (string) $this->valueOrError;
            throw new \RuntimeException('Called unwrap() on Err: ' . $msg);
        }

        return $this->valueOrError;
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->isOk ? $this->valueOrError : $default;
    }

    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->isOk ? $this->valueOrError : $fn($this->valueOrError);
    }

    /** @throws \RuntimeException when called on Ok */
    public function unwrapErr(): mixed
    {
        if ($this->isOk) {
            throw new \RuntimeException('Called unwrapErr() on Ok');
        }

        return $this->valueOrError;
    }

    // -----------------------------------------------------------------------
    // Functional combinators
    // -----------------------------------------------------------------------

    /** Apply $f to the Ok value; pass Err through unchanged. */
    public function map(callable $f): self
    {
        return $this->isOk ? self::ok($f($this->valueOrError)) : $this;
    }

    /** Apply $f to the Err value; pass Ok through unchanged. */
    public function mapErr(callable $f): self
    {
        return $this->isOk ? $this : self::err($f($this->valueOrError));
    }

    /** Apply $f that itself returns a Result (monadic bind / flatMap). */
    public function flatMap(callable $f): self
    {
        return $this->isOk ? $f($this->valueOrError) : $this;
    }

    /** Alias for flatMap (common convention). */
    public function andThen(callable $f): self
    {
        return $this->flatMap($f);
    }

    /**
     * Return this Result if it is Ok, otherwise call $f with the error and
     * return whatever it yields.
     */
    public function orElse(callable $f): self
    {
        return $this->isOk ? $this : $f($this->valueOrError);
    }

    /**
     * Pattern-match on both variants in one expression.
     *
     * @param callable $onOk  fn(mixed $value): mixed
     * @param callable $onErr fn(mixed $error): mixed
     */
    public function match(callable $onOk, callable $onErr): mixed
    {
        return $this->isOk
            ? $onOk($this->valueOrError)
            : $onErr($this->valueOrError);
    }

    /**
     * Thread the Ok value through a sequence of callables.
     * Each callable receives the current Ok value and returns a new Result.
     * Short-circuits on first Err.
     *
     * @param callable ...$stages fn(mixed): Result
     */
    public function pipe(callable ...$stages): self
    {
        $current = $this;
        foreach ($stages as $stage) {
            if ($current->isErr()) {
                return $current;
            }
            $next = $stage($current->valueOrError);
            if (!$next instanceof self) {
                $next = self::ok($next);
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * Convert to a nullable value: Ok($v) → $v, Err → null.
     * Useful for interop with code that uses null-checks.
     */
    public function toOption(): mixed
    {
        return $this->isOk ? $this->valueOrError : null;
    }

    /**
     * Return the Ok value if both this and $other are Ok.
     * Equivalent to Rust's Result::and().
     */
    public function and(self $other): self
    {
        return $this->isOk ? $other : $this;
    }

    /**
     * Return this Result if Ok, otherwise $other.
     * Equivalent to Rust's Result::or().
     */
    public function or(self $other): self
    {
        return $this->isOk ? $this : $other;
    }

    // -----------------------------------------------------------------------
    // Interop helpers
    // -----------------------------------------------------------------------

    /**
     * Collect an array of Results into a Result<array>.
     * First Err encountered short-circuits and is returned.
     *
     * @param self[] $results
     */
    public static function collect(array $results): self
    {
        $values = [];
        foreach ($results as $result) {
            if ($result->isErr()) {
                return $result;
            }
            $values[] = $result->unwrap();
        }

        return self::ok($values);
    }

    /**
     * Convert a nullable value to a Result.
     * $value !== null → Ok($value), null → Err($error).
     */
    public static function fromNullable(mixed $value, mixed $error = 'Value was null'): self
    {
        return $value !== null ? self::ok($value) : self::err($error);
    }
}
