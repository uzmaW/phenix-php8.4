<?php
namespace Phoenix\CQRS;

abstract class Command
{
    abstract public function getCommandName(): string;

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
