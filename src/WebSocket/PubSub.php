<?php
namespace Phoenix\WebSocket;

final class PubSub
{
    private string $channel;
    private string $pubsubDir;
    private array $subscribers = [];
    private static int $counter = 0;

    public function __construct(string $channel = 'phoenix', ?string $dir = null)
    {
        $this->channel = $channel;
        $this->pubsubDir = $dir ?? sys_get_temp_dir() . '/phoenix_pubsub_' . $channel;
        if (!is_dir($this->pubsubDir)) {
            mkdir($this->pubsubDir, 0755, true);
        }
    }

    public function publish(array $message): void
    {
        $data = json_encode($message);
        self::$counter++;
        $filename = $this->pubsubDir . '/' . str_pad((string)self::$counter, 10, '0', STR_PAD_LEFT) . '.json';
        file_put_contents($filename, $data);
        $this->cleanup(10);
    }

    public function subscribe(callable $callback): void
    {
        $this->subscribers[] = $callback;
    }

    public function readMessages(): array
    {
        $messages = [];
        $files = glob($this->pubsubDir . '/*.json');
        if ($files) {
            sort($files);
            foreach ($files as $file) {
                $data = file_get_contents($file);
                if ($data !== false) {
                    $decoded = json_decode($data, true);
                    if ($decoded !== null) {
                        $messages[] = $decoded;
                    }
                }
                unlink($file);
            }
        }
        return $messages;
    }

    private function cleanup(int $maxFiles = 10): void
    {
        $files = glob($this->pubsubDir . '/*.json');
        if (count($files) > $maxFiles) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $toRemove = array_slice($files, 0, count($files) - $maxFiles);
            foreach ($toRemove as $file) {
                @unlink($file);
            }
        }
    }
}
