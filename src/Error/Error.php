<?php
declare(strict_types=1);

namespace Phoenix\Error;

/**
 * Base domain error for the Error/Result pattern.
 * Paired with Phoenix\Core\Result for fallible domain operations.
 */
class Error
{
    public function __construct(
        protected string $message,
        protected mixed $context = null,
    ) {}

    public function get_message(): string
    {
        return $this->message;
    }

    public function get_context(): mixed
    {
        return $this->context;
    }

    public function __toString(): string
    {
        return static::class . ': ' . $this->message;
    }
}

/** Input failed business validation */
class ValidationError extends Error
{
    public function __construct(string $message, public readonly array $validation_errors = [])
    {
        parent::__construct($message, $validation_errors);
    }
}

/** Requested resource does not exist */
class NotFoundError extends Error
{
    public function __construct(string $resource, mixed $identifier)
    {
        parent::__construct(
            sprintf('%s with identifier "%s" not found', $resource, (string) $identifier),
            ['resource' => $resource, 'identifier' => $identifier],
        );
    }
}

/** Caller is not allowed to perform the action */
class UnauthorizedError extends Error
{
    public function __construct(string $action)
    {
        parent::__construct(sprintf('Unauthorized to perform action: %s', $action));
    }
}

/** Business rule rejected the operation */
class BusinessLogicError extends Error
{
    public function __construct(string $message, public readonly ?string $business_code = null)
    {
        parent::__construct($message, ['business_code' => $business_code]);
    }
}
