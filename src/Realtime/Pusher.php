<?php
namespace Phoenix\Realtime;

final class Pusher
{
    private array $channels = [];
    private array $events = [];

    public function __construct(private string $driver = 'log') {}

    public function channel(string $name): void
    {
        $this->channels[$name] = true;
    }

    public function trigger(string $channel, string $event, array $data): void
    {
        $this->events[] = [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'time' => time(),
        ];

        if ($this->driver === 'log') {
            error_log("[Pusher] {$channel}: {$event} " . json_encode($data));
        }
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getChannels(): array
    {
        return array_keys($this->channels);
    }
}
