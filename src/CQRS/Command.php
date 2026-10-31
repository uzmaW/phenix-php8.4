<?php
declare(strict_types=1);
namespace Phoenix\CQRS;

/**
 * Marker interface – all command objects must implement this.
 */
interface CommandInterface {}

/**
 * Abstract base class for commands.
 * Implements CommandInterface so concrete commands can extend this class and
 * be dispatched through CommandBus without extra boilerplate.
 */
abstract class Command implements CommandInterface
{
    /**
     * Return a human-readable name for this command.
     * Defaults to the short class name; override for custom names.
     */
    public function getCommandName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
