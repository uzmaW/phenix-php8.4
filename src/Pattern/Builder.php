<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

/**
 * Builder interface for fluent object construction
 * Inspired by Rust-like PHP framework's Builder pattern
 */
interface Builder
{
    /** Build the final object */
    public function build(): object;
}

/**
 * Fluent builder base class
 */
abstract class BaseBuilder
{
    use MarkerTrait;

    public function __construct() {}

    /** Enable method chaining */
    public function and(): self
    {
        return $this;
    }

    /** Validate the object being built */
    abstract protected function validate(): array;

    /** Build the final object */
    abstract public function build(): object;
}

/**
 * Generic fluent builder
 */
class FluentBuilder
{
    private array $properties = [];
    private array $validators = [];

    public function __construct(private string $class_name) {}

    public function set(string $property, mixed $value): self
    {
        $this->properties[$property] = $value;
        return $this;
    }

    public function validate(callable $validator): self
    {
        $this->validators[] = $validator;
        return $this;
    }

    public function build(): object
    {
        foreach ($this->validators as $validator) {
            $validation_result = $validator($this->properties);
            if (!$validation_result) {
                throw new \InvalidArgumentException('Validation failed');
            }
        }

        return new $this->class_name(...array_values($this->properties));
    }
}

/**
 * Static factory methods for common builders
 */
class BuilderFactory
{
    public static function create(string $class): FluentBuilder
    {
        return new FluentBuilder($class);
    }
}

/**
 * Complex builder with nested objects
 */
class ComplexBuilder extends BaseBuilder
{
    private array $build_steps = [];
    private array $post_build_hooks = [];

    public function add_step(string $name, callable $step): self
    {
        $this->build_steps[$name] = $step;
        return $this;
    }

    public function add_post_build_hook(callable $hook): self
    {
        $this->post_build_hooks[] = $hook;
        return $this;
    }

    protected function validate(): array
    {
        $errors = [];

        foreach ($this->build_steps as $step) {
            $step_result = $step();
            if (!$step_result) {
                $errors[] = 'Step failed';
            }
        }

        return $errors;
    }

    public function build(): object
    {
        $validation_errors = $this->validate();
        if (!empty($validation_errors)) {
            throw new \InvalidArgumentException('Build validation failed: ' . implode(', ', $validation_errors));
        }

        $object_data = [];
        foreach ($this->build_steps as $step) {
            $step_result = $step();
            if ($step_result) {
                $object_data = array_merge($object_data, $step_result);
            }
        }

        $object = new $this->target_class(...$object_data);

        foreach ($this->post_build_hooks as $hook) {
            $hook($object);
        }

        return $object;
    }
}