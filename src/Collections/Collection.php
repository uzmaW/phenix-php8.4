<?php
declare(strict_types=1);

namespace Phoenix\Collections;

/**
 * Collection interface
 * Inspired by Rust-like PHP framework's Collections
 */
interface Collection
{
    /** Check if collection is empty */
    public function is_empty(): bool;
    /** Get number of items */
    public function count(): int;
    /** Convert to array */
    public function to_array(): array;
    /** Get first item */
    public function first(): mixed;
    /** Get last item */
    public function last(): mixed;
    /** Get iterator */
    public function get_iterator(): \Iterator;
    /** Clear cache */
    public function clear_cache(): void;
    /** Get performance metrics */
    public function get_performance_metrics(): array;

    /** Add item to collection */
    public function add(mixed $item): self;
    /** Add multiple items */
    public function add_many(array $items): self;
    /** Remove item at index */
    public function remove_at(int $index): self;
    /** Remove first occurrence of item */
    public function remove_first(mixed $item): self;
}