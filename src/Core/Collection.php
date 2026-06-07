<?php

namespace Phoenix\Core;

final class Collection implements \IteratorAggregate, \Countable
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first(callable $callback = null): mixed
    {
        if ($callback) {
            foreach ($this->items as $item) {
                if ($callback($item)) return $item;
            }
            return null;
        }
        return $this->items[0] ?? null;
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }
}
