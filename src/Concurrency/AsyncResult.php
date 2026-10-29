<?php
declare(strict_types=1);

namespace Phoenix\Concurrency;

use Phoenix\Core\MarkerTrait;

/**
 * AsyncResult/Future pattern for asynchronous computation
 * Inspired by Rust-like PHP framework's AsyncResult
 */
abstract class AsyncResult
{
    use MarkerTrait;

    private string $status = "pending";
    private mixed $value;
    private ?\Exception $error = null;
    private $callback;
    private $errback;

    abstract public function wait(float $timeout = -1): bool;
    abstract public function get(): mixed;
    abstract public function is_resolved(): bool;
    abstract public function is_rejected(): bool;
    abstract public function wait_for(float $timeout = -1): self;

    /** Create a successful result */
    public static function ok(mixed $value): self
    {
        $instance = new class($value) extends AsyncResult {
            private $resolvedValue;

            public function __construct(mixed $value) {
                $this->resolvedValue = $value;
                $this->status = "resolved";
            }

            public function wait(float $timeout = -1): bool {
                return true;
            }

            public function get(): mixed {
                return $this->resolvedValue;
            }

            public function is_resolved(): bool {
                return $this->status === "resolved";
            }

            public function is_rejected(): bool {
                return $this->status === "rejected";
            }

            public function wait_for(float $timeout = -1): self {
                return $this;
            }
        };
        return $instance;
    }

    /** Create an error result */
    public static function err(\Exception $error): self
    {
        $instance = new class($error) extends AsyncResult {
            private $errorValue;

            public function __construct(\Exception $error) {
                $this->errorValue = $error;
                $this->status = "rejected";
            }

            public function wait(float $timeout = -1): bool {
                if ($timeout > 0) {
                    $start = microtime(true);
                    while (!($this->is_rejected())) {
                        if ((microtime(true) - $start) >= $timeout) {
                            return false;
                        }
                        usleep(10000);
                    }
                }
                return true;
            }

            public function get(): mixed {
                return $this->errorValue;
            }

            public function is_resolved(): bool {
                return $this->status === "resolved";
            }

            public function is_rejected(): bool {
                return $this->status === "rejected";
            }

            public function wait_for(float $timeout = -1): self {
                $this->wait($timeout);
                return $this;
            }
        };
        return $instance;
    }

    public function then(callable $on_resolved, callable $on_rejected = null): self
    {
        if ($this->is_resolved()) {
            return $on_resolved($this->get());
        }
        if ($this->is_rejected() && $on_rejected) {
            return $on_rejected($this->get_error());
        }
        return self::pending($on_resolved, $on_rejected);
    }

    public function catch_(callable $on_rejected): self
    {
        if ($this->is_rejected()) {
            return $on_rejected($this->get_error());
        }
        return $this;
    }

    public function is_ok(): bool {
        return $this->is_resolved();
    }

    public function is_err(): bool {
        return $this->is_rejected();
    }

    public function get_error(): ?\Exception {
        return $this->error;
    }

    private static function pending(callable $on_resolved, callable $on_rejected): self
    {
        $instance = new class extends AsyncResult {
            private $on_resolved;
            private $on_rejected;

            public function __construct(callable $on_resolved, callable $on_rejected = null) {
                $this->on_resolved = $on_resolved;
                $this->on_rejected = $on_rejected;
            }

            public function wait(float $timeout = -1): bool {
                return true;
            }

            public function get(): mixed {
                return null;
            }

            public function is_resolved(): bool {
                return false;
            }

            public function is_rejected(): bool {
                return false;
            }

            public function wait_for(float $timeout = -1): self {
                if ($timeout > 0) {
                    $start = microtime(true);
                    while (!($this->is_resolved() || $this->is_rejected())) {
                        if ((microtime(true) - $start) >= $timeout) {
                            return $this;
                        }
                        usleep(10000);
                    }
                }
                return $this;
            }
        };
        return $instance;
    }
}