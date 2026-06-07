<?php
namespace Phoenix\RateLimit;

final class RateLimiter
{
    private string $storageDir;
    private const CLEANUP_INTERVAL = 300;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/phoenix_rate_limit';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        $path = $this->storageDir . '/' . md5($key) . '.json';
        $now = time();

        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true) ?? $data;
        }

        if ($data['reset_at'] < $now) {
            $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
        }

        $data['count']++;
        file_put_contents($path, json_encode($data));

        $this->cleanupIfNeeded();

        return $data['count'] <= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts = 60, int $decaySeconds = 60): int
    {
        $path = $this->storageDir . '/' . md5($key) . '.json';
        $now = time();

        if (!file_exists($path)) return $maxAttempts;

        $data = json_decode(file_get_contents($path), true);
        if (!$data || ($data['reset_at'] ?? 0) < $now) return $maxAttempts;

        return max(0, $maxAttempts - ($data['count'] ?? 0));
    }

    public function reset(string $key): void
    {
        $path = $this->storageDir . '/' . md5($key) . '.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function middleware(string $keyPrefix = 'api'): callable
    {
        $limiter = $this;
        return function () use ($limiter, $keyPrefix) {
            $key = $keyPrefix . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (!$limiter->attempt($key)) {
                http_response_code(429);
                header('Retry-After: 60');
                echo json_encode(['error' => 'Too Many Requests']);
                exit;
            }
        };
    }

    private function cleanupIfNeeded(): void
    {
        $cleanupFile = $this->storageDir . '/.last_cleanup';
        if (file_exists($cleanupFile)) {
            $lastCleanup = (int)file_get_contents($cleanupFile);
            if (time() - $lastCleanup < self::CLEANUP_INTERVAL) return;
        }
        file_put_contents($cleanupFile, (string)time());

        $files = glob($this->storageDir . '/*.json');
        $now = time();
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && ($data['reset_at'] ?? 0) < $now) {
                @unlink($file);
            }
        }
    }
}
