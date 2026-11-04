<?php
declare(strict_types=1);
namespace Phoenix\EventSourcing;

use Phoenix\Core\Result;

/**
 * File-backed Event Store with:
 *  - Optimistic concurrency / version-conflict detection
 *  - Live projection updates on save
 *  - Full projection replay and rebuild
 */
final class EventStore
{
    private string $storageDir;

    /** @var array<string, Projection> */
    private array $projections = [];

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/phoenix_events';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    // -------------------------------------------------------------------------
    // Projections
    // -------------------------------------------------------------------------

    public function addProjection(Projection $projection): void
    {
        $this->projections[$projection->getName()] = $projection;
    }

    public function getProjection(string $name): ?Projection
    {
        return $this->projections[$name] ?? null;
    }

    // -------------------------------------------------------------------------
    // Persistence
    // -------------------------------------------------------------------------

    /**
     * Persist all uncommitted events from the aggregate.
     *
     * Optimistic locking: if $expectedVersion is provided, the current
     * persisted event count must match; otherwise Result::err() is returned
     * without writing anything (caller should retry after reload).
     *
     * @return Result Ok(value is the number of events saved) or Err(value is conflict message)
     */
    public function save(AggregateRoot $aggregate, ?int $expectedVersion = null): Result
    {
        $events = $aggregate->releaseEvents();

        if (empty($events)) {
            return Result::ok(0);
        }

        $path     = $this->aggregatePath($aggregate->getId());
        $existing = $this->readFile($path);

        // Optimistic locking check
        if ($expectedVersion !== null) {
            $currentVersion = count($existing);
            if ($currentVersion !== $expectedVersion) {
                return Result::err(sprintf(
                    'Version conflict: expected version %d but found %d for aggregate %s',
                    $expectedVersion,
                    $currentVersion,
                    $aggregate->getId(),
                ));
            }
        }

        foreach ($events as $event) {
            $eventData  = $event->toArray();
            $existing[] = $eventData;

            // Keep projections up-to-date in real-time
            foreach ($this->projections as $projection) {
                $projection->apply($eventData);
            }
        }

        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT));

        return Result::ok(count($events));
    }

    /**
     * Load all persisted raw event arrays for a given aggregate.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadEvents(string $aggregateId): array
    {
        return $this->readFile($this->aggregatePath($aggregateId));
    }

    // -------------------------------------------------------------------------
    // Projection replay
    // -------------------------------------------------------------------------

    /**
     * Reset and replay all events for one aggregate through every registered
     * projection.
     */
    public function replayProjections(string $aggregateId): void
    {
        $events = $this->loadEvents($aggregateId);

        foreach ($this->projections as $projection) {
            $projection->reset();
            foreach ($events as $eventData) {
                $projection->apply($eventData);
            }
        }
    }

    /**
     * Full rebuild: reset every projection then replay ALL stored event files.
     *
     * @return int Total number of events replayed.
     */
    public function rebuildAllProjections(): int
    {
        foreach ($this->projections as $projection) {
            $projection->reset();
        }

        $count = 0;

        foreach (glob($this->storageDir . '/*.json') ?: [] as $file) {
            $events = json_decode((string) file_get_contents($file), true) ?? [];
            foreach ($events as $eventData) {
                foreach ($this->projections as $projection) {
                    $projection->apply($eventData);
                }
                $count++;
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function aggregatePath(string $aggregateId): string
    {
        // Sanitise the id to avoid directory traversal
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $aggregateId);
        return $this->storageDir . '/' . $safe . '.json';
    }

    private function readFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        return json_decode((string) file_get_contents($path), true) ?? [];
    }
}
