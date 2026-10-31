<?php
declare(strict_types=1);
namespace Phoenix\Core;

use Psr\Container\ContainerInterface;

/**
 * PSR-11 Container with:
 *  - Tagged bindings   (tag / tagged)
 *  - Contextual bindings (when / needs / give)
 *  - Circular dependency detection
 *  - bind() / singleton() convenience wrappers
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $services = [];

    /** @var array<string, mixed> – resolved singletons */
    private array $instances = [];

    /** @var array<string, list<string>> – tag => [service ids] */
    private array $tags = [];

    /**
     * Contextual bindings.
     * $contextual[$parent][$abstract] = $concrete
     *
     * @var array<string, array<string, string>>
     */
    private array $contextual = [];

    /** Cache of class-existence checks to avoid repeated reflection. */
    private array $failedLookups = [];

    /** Tracks which IDs are currently being resolved (for cycle detection). */
    private array $resolving = [];

    // -------------------------------------------------------------------------
    // Binding
    // -------------------------------------------------------------------------

    /**
     * Register a factory callable for the given id.
     * Re-registration clears any cached singleton instance.
     */
    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
        unset($this->instances[$id], $this->failedLookups[$id]);
    }

    /**
     * Bind an abstract (interface/alias) to a concrete class name.
     * The concrete class will be resolved through the container itself.
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->set($abstract, fn(Container $c) => $c->get($concrete));
    }

    /**
     * Singleton binding – the factory is called once and the result is cached.
     * (All `set()` registrations behave as singletons; this alias makes intent explicit.)
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->set($id, $factory);
    }

    /**
     * Start a contextual binding chain:
     *   $container->when(ClassA::class)->needs(InterfaceB::class)->give(ImplC::class);
     */
    public function when(string $parent): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $parent);
    }

    /** Called internally by ContextualBindingBuilder. */
    public function addContextual(string $parent, string $abstract, string $concrete): void
    {
        $this->contextual[$parent][$abstract] = $concrete;
    }

    // -------------------------------------------------------------------------
    // Tagging
    // -------------------------------------------------------------------------

    /**
     * Associate multiple service ids with a tag label.
     *
     * @param list<string> $ids
     */
    public function tag(string $tag, array $ids): void
    {
        foreach ($ids as $id) {
            $this->tags[$tag][] = $id;
        }
    }

    /**
     * Resolve and return all services registered under $tag.
     *
     * @return list<mixed>
     */
    public function tagged(string $tag): array
    {
        $result = [];
        foreach ($this->tags[$tag] ?? [] as $id) {
            $result[] = $this->get($id);
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Resolution  (PSR-11)
    // -------------------------------------------------------------------------

    /**
     * Resolve a service by id.
     *
     * @param string      $id     Service identifier / class name.
     * @param string|null $parent The id of the caller (enables contextual binding).
     */
    public function get(string $id, ?string $parent = null): mixed
    {
        // 1. Check contextual override for this (parent, abstract) pair
        if ($parent !== null && isset($this->contextual[$parent][$id])) {
            return $this->get($this->contextual[$parent][$id]);
        }

        // 2. Return cached singleton instance
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // 3. Circular dependency guard
        if (isset($this->resolving[$id])) {
            throw new \RuntimeException(
                "Circular dependency detected while resolving: {$id}"
            );
        }

        $this->resolving[$id] = true;
        try {
            // 4. Registered factory
            if (isset($this->services[$id])) {
                return $this->instances[$id] = ($this->services[$id])($this);
            }

            // 5. Previous failed lookup – skip class_exists overhead
            if (array_key_exists($id, $this->failedLookups)) {
                throw new \RuntimeException("Service '{$id}' not found");
            }

            // 6. Auto-wire a concrete class with a no-arg constructor
            if (class_exists($id)) {
                $this->failedLookups[$id] = false; // mark as "exists"
                return $this->instances[$id] = new $id();
            }

            $this->failedLookups[$id] = true;
            throw new \RuntimeException("Service '{$id}' not found");
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /** PSR-11: return true if the id can be resolved. */
    public function has(string $id): bool
    {
        if (isset($this->services[$id]) || array_key_exists($id, $this->instances)) {
            return true;
        }
        if (array_key_exists($id, $this->failedLookups)) {
            return $this->failedLookups[$id] === false;
        }
        return class_exists($id);
    }

    // -------------------------------------------------------------------------
    // Management
    // -------------------------------------------------------------------------

    /** Remove a single binding and its cached instance. */
    public function forget(string $id): void
    {
        unset($this->services[$id], $this->instances[$id], $this->failedLookups[$id]);
    }

    /** Wipe the entire container state. */
    public function clear(): void
    {
        $this->services      = [];
        $this->instances     = [];
        $this->tags          = [];
        $this->contextual    = [];
        $this->failedLookups = [];
        $this->resolving     = [];
    }
}

// ---------------------------------------------------------------------------
// Contextual binding fluent builder
// ---------------------------------------------------------------------------

final class ContextualBindingBuilder
{
    private string $abstract = '';

    public function __construct(
        private readonly Container $container,
        private readonly string $parent,
    ) {}

    /** The abstract type / interface the parent class depends on. */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;
        return $this;
    }

    /** The concrete class to inject when the parent needs the abstract. */
    public function give(string $concrete): void
    {
        $this->container->addContextual($this->parent, $this->abstract, $concrete);
    }
}
