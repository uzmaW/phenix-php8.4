<?php
declare(strict_types=1);
namespace Phoenix\CQRS;

/**
 * Marker interface – all query objects must implement this.
 */
interface QueryInterface {}

/**
 * Abstract base class for queries.
 * Implements QueryInterface so concrete queries can extend this class and
 * be dispatched through QueryBus without extra boilerplate.
 */
abstract class Query implements QueryInterface
{
    /**
     * Return a human-readable name for this query.
     * Defaults to the short class name; override for custom names.
     */
    public function getQueryName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
