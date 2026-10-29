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
    use MarkerTrait;

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
    use MarkerTrait, StructTagTrait;

    public function get_name(): string;
    public function get_payload(): array;
    public function get_source(): object;
}

/**
 * Base event implementation
 */
abstract class BaseEvent implements Event
{
    use StructTagTrait;

    protected const EVENT_NAME = '';

    public function __construct(
        protected object $source,
        protected array $payload = []
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
 * Generic subject implementation with performance optimizations
 */
class ObservableSubject implements Subject
{
    use StructTagTrait;

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

    public function __construct(private string $subject_name) {}

    public function attach(Observer $observer): void
    {
        self::$metrics['observers_attached']++;
        $observer_key = spl_object_hash($observer);
        $this->observers[$observer_key] = $observer;
    }

    public function detach(Observer $observer): void
    {
        self::$metrics['observers_detached']++;
        $observer_key = spl_object_hash($observer);
        unset($this->observers[$observer_key]);
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

    public function set_state(mixed $state): void
    {
        $this->state = $state;
    }

    public function get_state(): mixed
    {
        return $this->state;
    }

    public function set_batch_mode(bool $enabled): void
    {
        $this->batch_mode = $enabled;
    }

    public function is_batch_mode(): bool
    {
        return $this->batch_mode;
    }
}