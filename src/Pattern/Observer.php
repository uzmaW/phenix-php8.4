<?php
declare(strict_types=1);

namespace Phoenix\Pattern;

/**
 * Observer interface
 * Inspired by Rust-like PHP framework's Observer pattern
 */
interface Observer
{
    /** Update the subject */
    public function update(Subject $subject): void;
}

/**
 * Subject interface
 */
interface Subject
{
    public function attach(Observer $observer): void;
    public function detach(Observer $observer): void;
    public function notify(): void;
    public function get_state(): mixed;
}

/**
 * Event interface for richer notifications
 */
interface Event
{
    public function get_name(): string;
    public function get_payload(): array;
    public function get_source(): object;
    public function get_struct_tag(): string;
}

/**
 * Base event implementation
 */
abstract class BaseEvent implements Event
{
    protected const EVENT_NAME = '';

    public function __construct(
        protected object $source,
        protected array $payload = [],
    ) {}

    public function get_name(): string
    {
        return static::EVENT_NAME;
    }

    public function get_payload(): array
    {
        return $this->payload;
    }

    public function get_source(): object
    {
        return $this->source;
    }

    public function get_struct_tag(): string
    {
        return static::class;
    }
}

/**
 * Generic subject implementation with batch notification support
 */
class ObservableSubject implements Subject, \Countable
{
    private array $observers = [];
    private mixed $state;
    private array $event_queue = [];
    private bool $batch_mode = false;

    /** Performance metrics for observer operations */
    private static array $metrics = [
        'notifications_sent' => 0,
        'observers_attached' => 0,
        'observers_detached' => 0,
        'batch_operations' => 0,
    ];

    public static function get_metrics(): array
    {
        return self::$metrics;
    }

    public static function reset_metrics(): void
    {
        self::$metrics = [
            'notifications_sent' => 0,
            'observers_attached' => 0,
            'observers_detached' => 0,
            'batch_operations' => 0,
        ];
    }

    public function __construct(private readonly string $subject_name) {}

    public function get_name(): string
    {
        return $this->subject_name;
    }

    public function count(): int
    {
        return count($this->observers);
    }

    public function attach(Observer $observer): void
    {
        self::$metrics['observers_attached']++;
        $this->observers[spl_object_hash($observer)] = $observer;
    }

    public function detach(Observer $observer): void
    {
        self::$metrics['observers_detached']++;
        unset($this->observers[spl_object_hash($observer)]);
    }

    public function notify(): void
    {
        self::$metrics['notifications_sent']++;

        if ($this->batch_mode) {
            $this->event_queue[] = $this->state;

            return;
        }

        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * Flush queued batch notifications as a single update per observer
     * carrying every queued state.
     */
    public function flush(): int
    {
        if ($this->event_queue === []) {
            return 0;
        }

        self::$metrics['batch_operations']++;
        $queued = $this->event_queue;
        $this->event_queue = [];

        $previous_state = $this->state;
        $this->state = $queued;

        foreach ($this->observers as $observer) {
            $observer->update($this);
        }

        $this->state = $previous_state;

        return count($queued);
    }

    public function set_state(mixed $state): void
    {
        $this->state = $state;
    }

    public function get_state(): mixed
    {
        return $this->state;
    }

    public function pending_notifications(): int
    {
        return count($this->event_queue);
    }

    public function set_batch_mode(bool $enabled): void
    {
        $this->batch_mode = $enabled;
        if (!$enabled) {
            $this->flush();
        }
    }

    public function is_batch_mode(): bool
    {
        return $this->batch_mode;
    }
}
