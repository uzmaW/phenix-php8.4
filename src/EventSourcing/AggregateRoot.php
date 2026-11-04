<?php
declare(strict_types=1);
namespace Phoenix\EventSourcing;

/**
 * Enhanced AggregateRoot with:
 *  - Persisted-version tracking (for optimistic locking)
 *  - hasUncommittedChanges() helper
 *  - replayEvents() from raw arrays via GenericEvent
 */
abstract class AggregateRoot
{
    protected string $id              = '';
    protected array  $recordedEvents  = [];
    protected int    $version         = 0;
    private   int    $persistedVersion = 0;

    // -------------------------------------------------------------------------
    // Identity & version
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return $this->id;
    }

    /** In-memory version (includes uncommitted events). */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * The version as of the last releaseEvents() call (i.e. what is on disk).
     * Pass this to EventStore::save() as $expectedVersion for optimistic locking.
     */
    public function getPersistedVersion(): int
    {
        return $this->persistedVersion;
    }

    public function hasUncommittedChanges(): bool
    {
        return !empty($this->recordedEvents);
    }

    // -------------------------------------------------------------------------
    // Event recording
    // -------------------------------------------------------------------------

    /** Record a domain event, apply it immediately, and increment the version. */
    protected function recordThat(Event $event): void
    {
        $this->recordedEvents[] = $event;
        $this->apply($event);
        $this->version++;
    }

    /** Subclasses implement this to mutate state from an event. */
    abstract protected function apply(Event $event): void;

    // -------------------------------------------------------------------------
    // Event release (called by EventStore)
    // -------------------------------------------------------------------------

    /**
     * Hand off all recorded events to the store.
     * Clears the internal buffer and advances the persisted-version pointer.
     */
    public function releaseEvents(): array
    {
        $events                = $this->recordedEvents;
        $this->recordedEvents  = [];
        $this->persistedVersion = $this->version;
        return $events;
    }

    // -------------------------------------------------------------------------
    // Replay (reconstitution from storage)
    // -------------------------------------------------------------------------

    /**
     * Reconstitute state by replaying persisted events.
     *
     * Accepts either Event instances or raw arrays (wrapped in GenericEvent).
     *
     * @param array<int, Event|array<string,mixed>> $events
     */
    public function replayEvents(array $events): void
    {
        foreach ($events as $eventData) {
            if ($eventData instanceof Event) {
                $this->apply($eventData);
            } elseif (is_array($eventData)) {
                $this->apply(new GenericEvent($eventData));
            }
            $this->version++;
        }
        $this->persistedVersion = $this->version;
    }
}

// ---------------------------------------------------------------------------
// GenericEvent – used when replaying from raw persisted arrays
// ---------------------------------------------------------------------------

/**
 * Wraps a raw event array so it can be passed to apply() during replay.
 * Not intended to be recorded by domain logic directly.
 */
final class GenericEvent extends Event
{
    private array $data;

    public function __construct(array $data)
    {
        // Skip the parent constructor (which would generate a new eventId/occurredOn)
        // because we are reconstructing from persisted data.
        $this->data = $data;
    }

    public function getEventName(): string
    {
        return $this->data['event_name'] ?? $this->data['type'] ?? 'unknown';
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getType(): string
    {
        return $this->data['type'] ?? $this->data['event_name'] ?? 'unknown';
    }

    public function getPayload(): array
    {
        return $this->data['payload'] ?? [];
    }
}
