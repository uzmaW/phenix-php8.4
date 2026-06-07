<?php
namespace Phoenix\CQRS;

final class CommandBus
{
    private array $handlers = [];

    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(Command $command): mixed
    {
        $class = get_class($command);
        if (!isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler registered for command: $class");
        }
        return ($this->handlers[$class])($command);
    }

    public function hasHandler(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }
}
