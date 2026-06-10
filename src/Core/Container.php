<?php

namespace Phoenix\Core;

use Psr\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    private array $services = [];
    private array $instances = [];
    private array $failedLookups = [];

    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
        unset($this->failedLookups[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->services[$id])) {
            return $this->instances[$id] = ($this->services[$id])($this);
        }

        if (isset($this->failedLookups[$id])) {
            throw new \Exception("Service '$id' not found");
        }

        if (class_exists($id)) {
            $this->failedLookups[$id] = false;

            return $this->instances[$id] = new $id();
        }

        $this->failedLookups[$id] = true;

        throw new \Exception("Service '$id' not found");
    }

    public function has(string $id): bool
    {
        if (isset($this->services[$id]) || isset($this->instances[$id])) {
            return true;
        }

        if (isset($this->failedLookups[$id])) {
            return $this->failedLookups[$id] === false;
        }

        return class_exists($id);
    }

    public function forget(string $id): void
    {
        unset($this->services[$id], $this->instances[$id], $this->failedLookups[$id]);
    }

    public function clear(): void
    {
        $this->services = [];
        $this->instances = [];
        $this->failedLookups = [];
    }
}
