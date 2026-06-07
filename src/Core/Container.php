<?php

namespace Phoenix\Core;

use Psr\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    private array $services = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->services[$id])) {
            if (class_exists($id)) {
                return $this->instances[$id] = new $id();
            }
            throw new \Exception("Service '$id' not found");
        }

        return $this->instances[$id] = ($this->services[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || class_exists($id);
    }
}
