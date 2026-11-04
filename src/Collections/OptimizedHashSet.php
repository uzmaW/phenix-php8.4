<?php
declare(strict_types=1);

namespace Phoenix\Collections;

/**
 * High-performance HashSet (unique items) with set operations
 * Fast membership testing, optimized union/intersection/difference
 * Inspired by Rust-like PHP framework's OptimizedHashSet
 */
class OptimizedHashSet implements \IteratorAggregate, \Countable
{
    private const DEFAULT_CAPACITY = 16;
    private const LOAD_FACTOR = 0.7;
    private int $capacity;
    private array $items = [];
    private int $size = 0;
    private int $hash_count = 0;

    public function __construct(array $items = [])
    {
        $this->capacity = self::DEFAULT_CAPACITY;

        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function count(): int
    {
        return $this->size;
    }

    public function is_empty(): bool
    {
        return $this->size === 0;
    }

    /** Add item (no duplicates) */
    public function add(mixed $item): self
    {
        $this->hash_count++;
        $key = $this->hash_key($item);

        if (array_key_exists($key, $this->items)) {
            return $this;
        }

        $this->items[$key] = $item;
        $this->size++;

        if ($this->size >= $this->capacity * self::LOAD_FACTOR) {
            $this->resize();
        }

        return $this;
    }

    public function add_many(array $items): self
    {
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    /** Check if item exists */
    public function contains(mixed $item): bool
    {
        $this->hash_count++;

        return array_key_exists($this->hash_key($item), $this->items);
    }

    /** Remove item */
    public function remove(mixed $item): bool
    {
        $key = $this->hash_key($item);

        if (array_key_exists($key, $this->items)) {
            unset($this->items[$key]);
            $this->size--;

            if ($this->size > 0 && $this->size <= $this->capacity * 0.2) {
                $this->shrink();
            }

            return true;
        }

        return false;
    }

    /** Union with another HashSet */
    public function union(OptimizedHashSet $other): OptimizedHashSet
    {
        $result = new OptimizedHashSet();
        $result->add_many($this->to_array());
        $result->add_many($other->to_array());

        return $result;
    }

    /** Intersection with another HashSet */
    public function intersection(OptimizedHashSet $other): OptimizedHashSet
    {
        $result = new OptimizedHashSet();
        foreach ($this->items as $item) {
            if ($other->contains($item)) {
                $result->add($item);
            }
        }

        return $result;
    }

    /** Difference with another HashSet */
    public function difference(OptimizedHashSet $other): OptimizedHashSet
    {
        $result = new OptimizedHashSet();
        foreach ($this->items as $item) {
            if (!$other->contains($item)) {
                $result->add($item);
            }
        }

        return $result;
    }

    /**
     * Private hash key generation.
     * Scalars use their own value (stable + cheap), everything else is digested.
     */
    private function hash_key(mixed $item): string
    {
        if (is_string($item)) {
            return 's:' . $item;
        }

        if (is_int($item)) {
            return 'i:' . $item;
        }

        if (is_bool($item)) {
            return 'b:' . (int) $item;
        }

        if ($item === null) {
            return 'null';
        }

        if (is_float($item)) {
            return 'f:' . serialize($item);
        }

        return 'h:' . hash('xxh128', serialize($item));
    }

    /** Grow capacity (key digests are capacity independent, no rehash needed) */
    private function resize(): void
    {
        $this->capacity *= 2;
    }

    /** Shrink capacity if too empty */
    private function shrink(): void
    {
        $this->capacity = max(self::DEFAULT_CAPACITY, (int) ($this->capacity / 2));
    }

    /** to_array */
    public function to_array(): array
    {
        return array_values($this->items);
    }

    /** Get iterator (yields values only, internal digests stay hidden) */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->to_array());
    }

    /** Apply callback to every item, returns a new set */
    public function map(callable $callback): OptimizedHashSet
    {
        return new OptimizedHashSet(array_map($callback, $this->to_array()));
    }

    /** Keep items passing the callback, returns a new set */
    public function filter(callable $callback): OptimizedHashSet
    {
        return new OptimizedHashSet(array_values(array_filter($this->items, $callback)));
    }

    /** Performance metrics */
    public function get_performance_metrics(): array
    {
        return [
            'capacity' => $this->capacity,
            'size' => $this->size,
            'hash_count' => $this->hash_count,
            'memory_usage' => memory_get_usage(true),
        ];
    }
}
