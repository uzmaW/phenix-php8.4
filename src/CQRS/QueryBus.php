<?php
namespace Phoenix\CQRS;

final class QueryBus
{
    private array $handlers = [];

    public function register(string $queryClass, callable $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    public function handle(Query $query): mixed
    {
        $class = get_class($query);
        if (!isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler registered for query: $class");
        }
        return ($this->handlers[$class])($query);
    }
}
