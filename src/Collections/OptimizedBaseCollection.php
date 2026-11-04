<?php
declare(strict_types=1);

namespace Phoenix\Collections;

/**
 * Optimized Base Collection with memory management and performance tracking
 * Inspired by Rust-like PHP framework's OptimizedBaseCollection
 */
abstract class OptimizedBaseCollection implements Collection
{
    protected array $items = [];
    private ?int $cached_count = null;
    private array $performance_metrics = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
        $this->update_metrics();
    }

    public function is_empty(): bool
    {
        return $this->count() === 0;
    }

    public function count(): int
    {
        return $this->cached_count ??= count($this->items);
    }

    public function to_array(): array
    {
        return $this->items;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[array_key_last($this->items)] ?? null;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function clear_cache(): void
    {
        $this->cached_count = null;
        $this->performance_metrics = [];
    }

    public function get_performance_metrics(): array
    {
        return array_merge($this->performance_metrics, [
            'items_count' => $this->count(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cache_hits' => 0,
        ]);
    }

    protected function update_metrics(): void
    {
        $this->performance_metrics = [
            'created_at' => microtime(true),
            'items_count' => $this->count(),
            'memory_initial' => memory_get_usage(true),
        ];
    }

    protected function invalidate_cache(): void
    {
        $this->cached_count = null;
    }
}
