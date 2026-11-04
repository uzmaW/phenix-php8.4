<?php
declare(strict_types=1);

namespace Phoenix\Collections;

/**
 * High-performance Vector (list) collection with advanced optimizations
 * Dynamic capacity management, batch insert/remove, bounds-checked access
 * Inspired by Rust-like PHP framework's OptimizedVector
 */
class OptimizedVector extends OptimizedBaseCollection
{
    private const DEFAULT_CAPACITY = 16;
    private int $capacity;
    private int $length;
    private int $growth_factor = 2;
    private array $storage = [];
    private int $push_count = 0;
    private int $pop_count = 0;
    private int $batch_count = 0;

    public function __construct(array $items = [], ?int $capacity = null)
    {
        $this->capacity = max(self::DEFAULT_CAPACITY, $capacity ?? 0, count($items));
        $this->storage = $items;
        $this->length = count($items);
        $this->push_count = $this->length;

        $this->update_metrics();
    }

    /** Optimized push operation with dynamic capacity management */
    public function push(mixed $item): self
    {
        $this->push_count++;
        $this->storage[] = $item;
        $this->length++;
        if ($this->length > $this->capacity) {
            $this->capacity = $this->length * $this->growth_factor;
        }

        // Track performance every 1000 pushes
        if ($this->push_count % 1000 === 0) {
            $this->update_metrics();
        }

        return $this;
    }

    /** Batch insert multiple items */
    public function push_batch(array $items): self
    {
        if ($items === []) {
            return $this;
        }

        $this->batch_count++;
        foreach ($items as $item) {
            $this->storage[] = $item;
            $this->length++;
        }
        $this->push_count += count($items);

        $this->invalidate_cache();

        return $this;
    }

    /** Insert item at specific position */
    public function insert_at(int $index, mixed $item): self
    {
        if ($index < 0 || $index > $this->length) {
            throw new \InvalidArgumentException("Index $index out of range");
        }

        array_splice($this->storage, $index, 0, [$item]);
        $this->length++;
        $this->invalidate_cache();

        return $this;
    }

    /** Remove item at index and shift left */
    public function remove_at(int $index): self
    {
        if ($index < 0 || $index >= $this->length) {
            throw new \InvalidArgumentException("Index $index out of range");
        }

        array_splice($this->storage, $index, 1);
        $this->length--;
        $this->invalidate_cache();

        return $this;
    }

    /** Optimized pop operation (remove from end) */
    public function pop(): mixed
    {
        if ($this->is_empty()) {
            return null;
        }

        $item = array_pop($this->storage);
        $this->length--;
        $this->pop_count++;
        $this->invalidate_cache();

        return $item;
    }

    /** Get item at index with bounds checking */
    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= $this->length) {
            throw new \OutOfBoundsException("Index $index out of bounds");
        }

        return $this->storage[$index];
    }

    /** Set item at index with bounds checking */
    public function set(int $index, mixed $item): self
    {
        if ($index < 0 || $index >= $this->length) {
            throw new \OutOfBoundsException("Index $index out of bounds");
        }

        $this->storage[$index] = $item;

        return $this;
    }

    /** Search for item index */
    public function index_of(mixed $item): int
    {
        $index = array_search($item, $this->storage, true);

        return $index === false ? -1 : $index;
    }

    /** Check if item exists */
    public function contains(mixed $item): bool
    {
        return $this->index_of($item) !== -1;
    }

    /** Add item to collection */
    public function add(mixed $item): self
    {
        return $this->push($item);
    }

    /** Add multiple items */
    public function add_many(array $items): self
    {
        foreach ($items as $item) {
            $this->push($item);
        }

        return $this;
    }

    /** Remove first occurrence of item */
    public function remove_first(mixed $item): self
    {
        $key = $this->index_of($item);
        if ($key !== -1) {
            $this->remove_at($key);
        }

        return $this;
    }

    /** Apply callback to every item, returns a new vector */
    public function map(callable $callback): self
    {
        $mapped = [];
        for ($i = 0; $i < $this->length; $i++) {
            $mapped[] = $callback($this->storage[$i], $i);
        }

        return new self($mapped, $this->capacity);
    }

    /** Keep items passing the callback, returns a new vector */
    public function filter(callable $callback): self
    {
        $filtered = [];
        for ($i = 0; $i < $this->length; $i++) {
            if ($callback($this->storage[$i], $i)) {
                $filtered[] = $this->storage[$i];
            }
        }

        return new self($filtered, $this->capacity);
    }

    /** Count of items */
    public function count(): int
    {
        return $this->length;
    }

    public function is_empty(): bool
    {
        return $this->length === 0;
    }

    public function to_array(): array
    {
        return $this->storage;
    }

    public function first(): mixed
    {
        return $this->length > 0 ? $this->storage[0] : null;
    }

    public function last(): mixed
    {
        return $this->length > 0 ? $this->storage[$this->length - 1] : null;
    }

    public function get_iterator(): \Iterator
    {
        return new \ArrayIterator($this->storage);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->storage);
    }

    /** Performance metrics */
    public function get_performance_metrics(): array
    {
        $metrics = parent::get_performance_metrics();
        $metrics = array_merge($metrics, [
            'capacity' => $this->capacity,
            'length' => $this->length,
            'push_count' => $this->push_count,
            'pop_count' => $this->pop_count,
            'batch_count' => $this->batch_count,
            'growth_factor' => $this->growth_factor,
            'memory_per_item' => $this->length > 0 ?
                (memory_get_usage(true) / $this->length) : 0,
        ]);

        return $metrics;
    }
}
