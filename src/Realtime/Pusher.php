<?php
namespace Phoenix\Realtime;

use Phoenix\WebSocket\PubSub;

final class Pusher
{
    private array $channels = [];
    private array $subscribers = [];
    private array $events = [];
    private PubSub $pubsub;
    private string $driver;

    public function __construct(string $driver = 'websocket', ?PubSub $pubsub = null)
    {
        $this->driver = $driver;
        $this->pubsub = $pubsub ?? new PubSub();
    }

    public function channel(string $name): void
    {
        $this->channels[$name] = true;
    }

    public function subscribe(string $channel, callable $callback): void
    {
        $this->subscribers[$channel][] = $callback;
    }

    public function trigger(string $channel, string $event, array $data): void
    {
        $payload = [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'time' => time(),
        ];

        $this->events[] = $payload;

        match ($this->driver) {
            'websocket' => $this->publishViaWebSocket($channel, $event, $data),
            'log' => $this->publishViaLog($channel, $event, $data),
            default => null,
        };

        $this->notifySubscribers($channel, $event, $data);
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    public function getSubscribedEvents(string $channel): array
    {
        return array_filter(
            $this->events,
            fn(array $e) => $e['channel'] === $channel
        );
    }

    private function publishViaWebSocket(string $channel, string $event, array $data): void
    {
        $this->pubsub->publish([
            'type' => 'pusher_event',
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'time' => time(),
        ]);
    }

    private function publishViaLog(string $channel, string $event, array $data): void
    {
        error_log("[Pusher] {$channel}: {$event} " . json_encode($data));
    }

    private function notifySubscribers(string $channel, string $event, array $data): void
    {
        foreach ($this->subscribers[$channel] ?? [] as $callback) {
            $callback(['event' => $event, 'data' => $data]);
        }
    }
}
