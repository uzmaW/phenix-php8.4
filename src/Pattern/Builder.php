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
abstract class BaseBuilder implements Builder
{
    /** Validate the object being built, returns list of error messages */
    abstract protected function validate(): array;

    /** Build the final object */
    abstract public function build(): object;

    /** Enable method chaining */
    public function and(): static
    {
        return $this;
    }
}

/**
 * Generic fluent builder. Constructor arguments are matched by name
 * (reflection), so property declaration order no longer matters.
 */
class FluentBuilder
{
    private array $properties = [];
    private array $validators = [];

    public function __construct(private readonly string $class_name) {}

    public function set(string $property, mixed $value): self
    {
        $this->properties[$property] = $value;

        return $this;
    }

    public function set_many(array $properties): self
    {
        foreach ($properties as $property => $value) {
            $this->properties[$property] = $value;
        }

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
            if (!($validator)($this->properties)) {
                throw new \InvalidArgumentException('Validation failed');
            }
        }

        return BuilderFactory::instantiate($this->class_name, $this->properties);
    }
}

/**
 * Complex builder with named build steps, target class and post-build hooks
 */
class ComplexBuilder extends BaseBuilder
{
    private array $build_steps = [];
    private array $post_build_hooks = [];

    public function __construct(private string $target_class) {}

    /** Target class built by this builder */
    public function target(string $class): self
    {
        $this->target_class = $class;

        return $this;
    }

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

        foreach ($this->build_steps as $name => $step) {
            try {
                $step_result = $step();
            } catch (\Throwable $e) {
                $errors[] = "Step '$name' threw: " . $e->getMessage();
                continue;
            }

            if ($step_result === false || $step_result === null) {
                $errors[] = "Step '$name' failed";
            }
        }

        return $errors;
    }

    public function build(): object
    {
        $validation_errors = $this->validate();
        if ($validation_errors !== []) {
            throw new \InvalidArgumentException('Build validation failed: ' . implode(', ', $validation_errors));
        }

        $object_data = [];
        foreach ($this->build_steps as $step) {
            $step_result = $step();
            if (is_array($step_result)) {
                $object_data = array_merge($object_data, $step_result);
            }
        }

        $object = BuilderFactory::instantiate($this->target_class, $object_data);

        foreach ($this->post_build_hooks as $hook) {
            $hook($object);
        }

        return $object;
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

    /**
     * Instantiate $class with named constructor arguments.
     * Argument order does not matter; unknown names fail fast with a
     * descriptive exception instead of an engine Error.
     */
    public static function instantiate(string $class, array $arguments): object
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class $class is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];
        $allowed = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters,
        );

        $unknown = array_diff(array_keys($arguments), $allowed);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown constructor argument(s) for %s: %s',
                $class,
                implode(', ', $unknown),
            ));
        }

        $missing = [];
        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()
                || array_key_exists($parameter->getName(), $arguments)
                || $parameter->isDefaultValueAvailable()
            ) {
                continue;
            }
            $missing[] = '$' . $parameter->getName();
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Missing required argument(s) for %s: %s',
                $class,
                implode(', ', $missing),
            ));
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
