<?php
declare(strict_types=1);

namespace Phoenix\Collections;

use Phoenix\Core\MarkerTrait;

/**
 * High-performance HashSet (unique items) with set operations
 * SHA-256 hashing for unique identification, optimized union/intersection/difference
 * Inspired by Rust-like PHP framework's OptimizedHashSet
 */
class OptimizedHashSet implements \IteratorAggregate, \Countable
{
    use MarkerTrait;

    private const DEFAULT_CAPACITY = 16;
    private int $capacity;
    private array $items = [];
    private int $size = 0;
    private int $hash_count = 0;

    public function __construct(array $items = [])
    {
        $this->capacity = self::DEFAULT_CAPACITY;

        foreach (array_unique($items) as $item) {
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

        if (isset($this->items[$key])) {
            return $this;
        }

        $this->items[$key] = $item;
        $this->size++;

        if ($this->size >= $this->capacity * 0.7) {
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
        $key = $this->hash_key($item);
        return isset($this->items[$key]);
    }

    /** Remove item */
    public function remove(mixed $item): bool
    {
        $key = $this->hash_key($item);
        if (isset($this->items[$key])) {
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
        foreach ($this->to_array() as $item) {
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
        foreach ($this->to_array() as $item) {
            if (!$other->contains($item)) {
                $result->add($item);
            }
        }
        return $result;
    }

    /** Private hash key generation */
    private function hash_key(mixed $item): string
    {
        return 'hash_' . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', serialize($item), true)));
    }

    /** Resize to larger capacity */
    private function resize(): void
    {
        $new_capacity = $this->capacity * 2;
        $new_items = [];

        foreach ($this->items as $key => $item) {
            $new_key = $this->hash_key($item);
            $new_items[$new_key] = $item;
        }

        $this->items = $new_items;
        $this->capacity = $new_capacity;
    }

    /** Shrink capacity if too empty */
    private function shrink(): void
    {
        $new_capacity = max(1, (int)($this->capacity / 2));
        $new_items = [];

        foreach ($this->items as $key => $item) {
            $new_key = $this->hash_key($item);
            $new_items[$new_key] = $item;
        }

        $this->items = $new_items;
        $this->capacity = $new_capacity;
    }

    /** to_array */
    public function to_array(): array
    {
        return array_values($this->items);
    }

    /** Get iterator */
    public function get_iterator(): \Iterator
    {
        return new \ArrayIterator($this->items);
    }

    /** Performance metrics */
    public function get_performance_metrics(): array
    {
        return [
            'capacity' => $this->capacity,
            'size' => $this->size,
            'hash_count' => $this->hash_count,
            'memory_usage' => memory_get_usage(true),
            'items' => $this->to_array(),
        ];
    }
}