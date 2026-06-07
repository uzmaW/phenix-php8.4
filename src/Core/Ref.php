<?php

namespace Phoenix\Core;

final class Ref
{
    private mixed $ref;

    public function __construct(mixed $value = null)
    {
        $this->ref = $value;
    }

    public function get(): mixed
    {
        return $this->ref;
    }

    public function set(mixed $value): void
    {
        $this->ref = $value;
    }
}
