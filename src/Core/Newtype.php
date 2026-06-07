<?php

namespace Phoenix\Core;

abstract class Newtype
{
    public function __construct(private readonly mixed $value) {}

    public function value(): mixed
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value && $this::class === $other::class;
    }
}
