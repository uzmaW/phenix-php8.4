<?php
namespace Phoenix\EventSourcing;

abstract class Event
{
    public readonly string $eventId;
    public readonly int $occurredOn;

    public function __construct()
    {
        $this->eventId = bin2hex(random_bytes(16));
        $this->occurredOn = time();
    }

    abstract public function getEventName(): string;

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->getEventName(),
            'occurred_on' => $this->occurredOn,
            'payload' => $this->getPayload()
        ];
    }

    protected function getPayload(): array
    {
        return get_object_vars($this);
    }
}
