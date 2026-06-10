<?php

namespace Phoenix\EventSourcing;

abstract class AggregateRoot
{
    protected string $id = '';
    protected array $recordedEvents = [];
    protected int $version = 0;

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    protected function recordThat(Event $event): void
    {
        $this->recordedEvents[] = $event;
        $this->apply($event);
        $this->version++;
    }

    abstract protected function apply(Event $event): void;

    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function replayEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->apply($event);
            $this->version++;
        }
    }
}
