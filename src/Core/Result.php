<?php

namespace Phoenix\Core;

final class Result
{
    private function __construct(
        private readonly bool $isOk,
        private readonly mixed $valueOrError
    ) {}

    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function err(mixed $error): self
    {
        return new self(false, $error);
    }

    public function isOk(): bool
    {
        return $this->isOk;
    }

    public function isErr(): bool
    {
        return !$this->isOk;
    }

    public function unwrap(): mixed
    {
        if (!$this->isOk) {
            throw new \RuntimeException('Called unwrap() on Err');
        }
        return $this->valueOrError;
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->isOk ? $this->valueOrError : $default;
    }

    public function map(callable $f): self
    {
        return $this->isOk ? self::ok($f($this->valueOrError)) : $this;
    }

    public function flatMap(callable $f): self
    {
        return $this->isOk ? $f($this->valueOrError) : $this;
    }
}
