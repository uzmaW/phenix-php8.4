<?php

namespace Phoenix\EventSourcing;

final class EventStore
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/phoenix_events';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0o755, true);
        }
    }

    public function save(AggregateRoot $aggregate): void
    {
        $events = $aggregate->releaseEvents();
        if (empty($events)) {
            return;
        }

        $path = $this->storageDir . '/' . $aggregate->getId() . '.json';
        $existing = [];
        if (file_exists($path)) {
            $existing = json_decode(file_get_contents($path), true) ?? [];
        }

        foreach ($events as $event) {
            $existing[] = $event->toArray();
        }

        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT));
    }

    public function loadEvents(string $aggregateId): array
    {
        $path = $this->storageDir . '/' . $aggregateId . '.json';
        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
