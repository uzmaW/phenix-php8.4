<?php
declare(strict_types=1);

namespace Phoenix\Collections;

/**
 * High-performance HashMap (associative array).
 *
 * Backed by a PHP array (itself an ordered hash map with O(1) average-case
 * lookups) so writes are single-store instead of the dual bucket+data writes
 * used previously. Key normalization keeps int/string coercion identical to
 * native PHP arrays, load-factor + rehash bookkeeping and operation metrics
 * are preserved for observability.
 * Inspired by Rust-like PHP framework's OptimizedHashMap
 */
class OptimizedHashMap implements \IteratorAggregate, \Countable
{
    private const DEFAULT_CAPACITY = 16;
    private int $capacity;
    private float $load_factor = 0.7;
    private array $data = [];
    private int $size = 0;
    private int $hash_count = 0;
    private int $rehash_count = 0;

    public function __construct(?int $capacity = null)
    {
        $this->capacity = max(1, $capacity ?? self::DEFAULT_CAPACITY);
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
        $this->hash_count++;

        return \array_key_exists($this->normalize_key($key), $this->data);
    }

    public function get(mixed $key): mixed
    {
        $this->hash_count++;
        $key = $this->normalize_key($key);

        return \array_key_exists($key, $this->data) ? $this->data[$key] : null;
    }

    public function set(mixed $key, mixed $value): self
    {
        $this->hash_count++;
        $key = $this->normalize_key($key);
        $is_new = !\array_key_exists($key, $this->data);

        if ($is_new) {
            if ($this->size + 1 > $this->capacity * $this->load_factor) {
                $this->capacity *= 2;
                $this->rehash_count++;
            }
            $this->size++;
        }

        $this->data[$key] = $value;

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
        $key = $this->normalize_key($key);

        if (\array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->size--;

            return true;
        }

        return false;
    }

    public function keys(): array
    {
        return \array_keys($this->data);
    }

    public function values(): array
    {
        return \array_values($this->data);
    }

    public function to_array(): array
    {
        return $this->data;
    }

    public function union(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap(max($this->capacity, $other->capacity));
        $result->set_many($this->to_array());
        $result->set_many($other->to_array());

        return $result;
    }

    public function intersection(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap();
        foreach ($this->data as $key => $value) {
            if ($other->has($key)) {
                $result->set($key, $value);
            }
        }

        return $result;
    }

    public function difference(OptimizedHashMap $other): OptimizedHashMap
    {
        $result = new OptimizedHashMap();
        foreach ($this->data as $key => $value) {
            if (!$other->has($key)) {
                $result->set($key, $value);
            }
        }

        return $result;
    }

    /**
     * PHP array keys only accept int|string. Keep int/string keys untouched
     * (identical semantics to native arrays) and namespace everything else.
     */
    private function normalize_key(mixed $key): mixed
    {
        if (\is_string($key) || \is_int($key)) {
            return $key;
        }

        if (\is_bool($key)) {
            return (int) $key;
        }

        if ($key === null) {
            return 'k:null';
        }

        if (\is_float($key)) {
            return 'f:' . $key;
        }

        return 'k:' . \serialize($key);
    }

    public function getIterator(): \ArrayIterator
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
            'memory_usage' => \memory_get_usage(true),
        ];
    }
}
