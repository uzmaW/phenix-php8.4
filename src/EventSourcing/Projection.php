<?php

declare(strict_types=1);

namespace Phoenix\EventSourcing;

/**
 * A Projection reads a stream of persisted event arrays and builds a read-model.
 *
 * Projections are registered with the EventStore and receive every event as it
 * is saved (live update), and can be fully rebuilt via rebuildAllProjections().
 */
interface Projection
{
    /** Unique name identifying this projection (used as the registry key). */
    public function getName(): string;

    /**
     * Apply one raw event (as persisted array) to update internal state.
     *
     * @param array<string, mixed> $eventData
     */
    public function apply(array $eventData): void;

    /** Return the current projected read-model state. */
    public function getState(): mixed;

    /** Reset to initial state (called before a full replay). */
    public function reset(): void;
}
