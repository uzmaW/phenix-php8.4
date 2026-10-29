<?php
declare(strict_types=1);

namespace Phoenix\Error;

use Phoenix\Core\MarkerTrait;

/** Successful result */
class Ok extends Result
{
    public function __construct(public mixed $value) { self::$metrics['ok_created']++; }
    public function is_ok(): bool { return true; }
    public function is_err(): bool { return false; }
    public function unwrap_or(mixed $default): mixed { return $this->value; }
    public function map(callable $fn): Result { self::$metrics['map_operations']++; return self::ok_fast($fn($this->value)); }
    public function map_err(callable $fn): Result { return $this; }
    public function unwrap_fast(): mixed { self::$metrics['unwrap_operations']++; return $this->value; }
    public function get_value(): mixed { return $this->value; }
}

/** Error result */
class Err extends Result
{
    public function __construct(public \Error $error) {}
    public function is_ok(): bool { return false; }
    public function is_err(): bool { return true; }
    public function unwrap_or(mixed $default): mixed { return $default; }
    public function map(callable $fn): Result { return $this; }
    public function map_err(callable $fn): Result { self::$metrics['map_operations']++; return new Err($fn($this->error)); }
    public function get_error(): \Error { return $this->error; }
}

/** Base error class */
abstract class Error
{
    use MarkerTrait;
    public function __construct(protected string $message, protected $context = null) {}
    public function get_message(): string { return $this->message; }
    public function get_context(): mixed { return $this->context; }
    public function __toString(): string { return $this->message; }
}

/** Specific error types */
class ValidationError extends Error { public function __construct(string $message, array $validation_errors = []) { parent::__construct($message, $validation_errors); } }
class NotFoundError extends Error { public function __construct(string $resource, mixed $identifier) { $message = sprintf('%s with identifier "%s" not found', $resource, $identifier); parent::__construct($message, ['resource' => $resource, 'identifier' => $identifier]); } }
class UnauthorizedError extends Error { public function __construct(string $action) { parent::__construct(sprintf('Unauthorized to perform action: %s', $action)); } }
class BusinessLogicError extends Error { public function __construct(string $message, ?string $business_code = null) { parent::__construct($message, ['business_code' => $business_code]); } }