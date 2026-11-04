<?php

declare(strict_types=1);

namespace Phoenix\CQRS;

use Phoenix\Core\Result;

/**
 * Middleware interface for the command pipeline.
 * Each middleware wraps the next handler in the chain.
 */
interface CommandMiddleware
{
    /**
     * @param  callable(CommandInterface): Result  $next
     */
    public function handle(CommandInterface $command, callable $next): Result;
}

/**
 * Logging middleware – logs command class name before/after dispatch.
 */
final class LoggingMiddleware implements CommandMiddleware
{
    private array $log = [];

    public function handle(CommandInterface $command, callable $next): Result
    {
        $class = get_class($command);
        $this->log[] = "dispatch:start:{$class}";
        $result = $next($command);
        $this->log[] = 'dispatch:end:' . ($result->isOk() ? 'ok' : 'err') . ":{$class}";

        return $result;
    }

    public function getLog(): array
    {
        return $this->log;
    }
}

/**
 * Transaction middleware – rolls back on Err results.
 * Uses a simple callable pair instead of a real DB transaction so
 * that it works in the test environment without a live database.
 */
final class TransactionMiddleware implements CommandMiddleware
{
    /** @var callable */
    private $begin;
    /** @var callable */
    private $commit;
    /** @var callable */
    private $rollback;

    public function __construct(
        callable $begin,
        callable $commit,
        callable $rollback,
    ) {
        $this->begin    = $begin;
        $this->commit   = $commit;
        $this->rollback = $rollback;
    }

    public function handle(CommandInterface $command, callable $next): Result
    {
        ($this->begin)();
        $result = $next($command);
        if ($result->isOk()) {
            ($this->commit)();
        } else {
            ($this->rollback)();
        }

        return $result;
    }
}

/**
 * Validation middleware – runs a registered validator for the command class
 * before passing it to the real handler.
 */
final class ValidationMiddleware implements CommandMiddleware
{
    /** @var array<string, callable(CommandInterface): ?string> */
    private array $validators = [];

    /** Register a validator for a command class. Return null = valid, string = error message. */
    public function addValidator(string $commandClass, callable $validator): void
    {
        $this->validators[$commandClass] = $validator;
    }

    public function handle(CommandInterface $command, callable $next): Result
    {
        $class = get_class($command);
        if (isset($this->validators[$class])) {
            $error = ($this->validators[$class])($command);
            if ($error !== null) {
                return Result::err($error);
            }
        }

        return $next($command);
    }
}

/**
 * Enhanced CommandBus with middleware pipeline and Result-returning handlers.
 */
final class CommandBus
{
    /** @var array<string, callable(CommandInterface): mixed> */
    private array $handlers = [];

    /** @var CommandMiddleware[] */
    private array $middleware = [];

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /** Append a middleware to the pipeline (executed LIFO around the core handler). */
    public function addMiddleware(CommandMiddleware $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function hasHandler(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }

    // -----------------------------------------------------------------------
    // Dispatch
    // -----------------------------------------------------------------------

    /**
     * Dispatch a command through the middleware pipeline.
     * Always returns a Result; legacy handlers whose return value is not a Result
     * are wrapped in Result::ok() automatically.
     */
    public function dispatch(CommandInterface $command): Result
    {
        $class = get_class($command);
        if (!isset($this->handlers[$class])) {
            return Result::err("No handler registered for command: {$class}");
        }

        // Build the pipeline (innermost handler wrapped outward)
        $core = function (CommandInterface $cmd) use ($class): Result {
            $raw = ($this->handlers[$class])($cmd);
            // Wrap non-Result returns for backward compatibility
            return $raw instanceof Result ? $raw : Result::ok($raw);
        };

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function (callable $next, CommandMiddleware $mw): callable {
                return static fn (CommandInterface $cmd): Result => $mw->handle($cmd, $next);
            },
            $core,
        );

        return $pipeline($command);
    }
}
