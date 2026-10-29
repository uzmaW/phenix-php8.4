<?php
declare(strict_types=1);

namespace Phoenix\Collections;

use Phoenix\Core\MarkerTrait;

/**
 * High-performance HashMap (associative array) with optimal bucket sizing
 * O(1) average case lookups, automatic rehashing, batch operations
 * Inspired by Rust-like PHP framework's OptimizedHashMap
 */
class OptimizedHashMap implements \IteratorAggregate, \Countable
{
    use MarkerTrait;

    private const DEFAULT_CAPACITY = 16;
    private int $capacity;
    private float $load_factor = 0.7;
    private array $buckets = [];
    private array $data = [];
    private int $size = 0;
    private int $hash_count = 0;
    private int $rehash_count = 0;

    public function __construct(int $capacity = null)
    {
        $this->capacity = $capacity ?? self::DEFAULT_CAPACITY;
        $this->buckets = array_fill(0, $this->capacity, []);
    }

    public function count(): int
    {
        return $this->size;
    }

    public function is_empty(): bool
    {
        return $this->size === 0;
    }

    public function has(mixed $key): bool
    {
        $index = $this->hash($key) % $this->capacity;
        return isset($this->buckets[$index][$key]);
    }

    public function get(mixed $key): mixed
    {
        $index = $this->hash($key) % $this->capacity;
        if (isset($this->buckets[$index][$key])) {
            return $this->buckets[$index][$key];
        }
        return null;
    }

    public function set(mixed $key, mixed $value): self
    {
        $this->hash_count++;
        if ($this->size >= $this->capacity * $this->load_factor) {
            $this->rehash();
        }
        $index = $this->hash($key) % $this->capacity;
        $this->buckets[$index][$key] = $value;
        $this->data[$key] = $value;
        $this->size++;
        return $this;
    }

    public function set_many(array $items): self
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function remove(mixed $key): bool
    {
        $index = $this->hash($key) % $this->capacity;
        if (isset($this->buckets[$index][$key])) {
            unset($this->buckets[$index][$key]);
            unset($this->data[$key]);
            $this->size--;
            $this->maybe_reindex();
            return true;
        }
        return false;
    }

    public function keys(): array
    {
        $keys = [];
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $key => $value) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public function values(): array
    {
        $values = [];
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $key => $value) {
                $values[] = $value;
            }
        }
        return $values;
    }

    public function to_array(): array
    {
        return $this->data;
    }

    public function union(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap($this->capacity + $other->capacity);
        $result->set_many($this->to_array());
        $result->set_many($other->to_array());
        return $result;
    }

    public function intersection(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap();
        foreach ($this->to_array() as $key => $value) {
            if ($other->has($key)) {
                $result->set($key, $value);
            }
        }
        return $result;
    }

    public function difference(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap();
        foreach ($this->to_array() as $key => $value) {
            if (!$other->has($key)) {
                $result->set($key, $value);
            }
        }
        return $result;
    }

    private function hash(mixed $key): int
    {
        return abs(crc32(serialize($key)));
    }

    private function rehash(): void
    {
        $old_buckets = $this->buckets;
        $old_capacity = $this->capacity;
        $this->capacity *= 2;
        $this->buckets = array_fill(0, $this->capacity, []);
        $this->data = [];
        $this->size = 0;
        $this->rehash_count++;
        foreach ($old_buckets as $bucket) {
            foreach ($bucket as $key => $value) {
                $index = $this->hash($key) % $this->capacity;
                $this->buckets[$index][$key] = $value;
                $this->data[$key] = $value;
                $this->size++;
            }
        }
    }

    private function maybe_reindex(): void
    {
        $new_buckets = [];
        foreach ($this->buckets as $i => $bucket) {
            $compacted = [];
            foreach ($bucket as $key => $value) {
                $compacted[$key] = $value;
            }
            $new_buckets[$i] = $compacted;
        }
        $this->buckets = $new_buckets;
    }

    public function get_iterator(): \Iterator
    {
        return new \ArrayIterator($this->data);
    }

    public function get_performance_metrics(): array
    {
        return [
            'capacity' => $this->capacity,
            'size' => $this->size,
            'load_factor' => $this->load_factor,
            'hash_count' => $this->hash_count,
            'rehash_count' => $this->rehash_count,
            'memory_usage' => memory_get_usage(true),
            'items' => $this->to_array(),
        ];
    }
}