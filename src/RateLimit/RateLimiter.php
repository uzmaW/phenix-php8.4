<?php
namespace Phoenix\RateLimit;

final class RateLimiter
{
    private string $storageDir;
    private bool $useMemory;
    private static array $memoryCache = [];
    private const CLEANUP_INTERVAL = 300;
    private const CLEANUP_BATCH_SIZE = 50;

    public function __construct(?string $storageDir = null, bool $useMemory = true)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/phoenix_rate_limit';
        $this->useMemory = $useMemory && function_exists('apcu_fetch') && (bool) ini_get('apc.enabled');

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        $now = time();

        if ($this->useMemory && isset(self::$memoryCache[$key])) {
            $data = self::$memoryCache[$key];
            if ($data['reset_at'] < $now) {
                $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
            }
            $data['count']++;
            self::$memoryCache[$key] = $data;
            $this->persistRateLimitData($key, $data);
            $this->cleanupIfNeeded();
            return $data['count'] <= $maxAttempts;
        }

        $path = $this->storageDir . '/' . md5($key) . '.json';
        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];

        if (file_exists($path)) {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw, true);
            if ($decoded && ($decoded['reset_at'] ?? 0) >= $now) {
                $data = $decoded;
            }
        }

        $data['count']++;
        file_put_contents($path, json_encode($data));

        if ($this->useMemory) {
            self::$memoryCache[$key] = $data;
        }

        $this->cleanupIfNeeded();
        return $data['count'] <= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts = 60, int $decaySeconds = 60): int
    {
        $now = time();

        if ($this->useMemory && isset(self::$memoryCache[$key])) {
            $data = self::$memoryCache[$key];
            if (($data['reset_at'] ?? 0) < $now) return $maxAttempts;
            return max(0, $maxAttempts - ($data['count'] ?? 0));
        }

        $path = $this->storageDir . '/' . md5($key) . '.json';
        if (!file_exists($path)) return $maxAttempts;

        $data = json_decode(file_get_contents($path), true);
        if (!$data || ($data['reset_at'] ?? 0) < $now) return $maxAttempts;

        return max(0, $maxAttempts - ($data['count'] ?? 0));
    }

    public function reset(string $key): void
    {
        if ($this->useMemory) {
            unset(self::$memoryCache[$key]);
        }

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

    private function persistRateLimitData(string $key, array $data): void
    {
        $path = $this->storageDir . '/' . md5($key) . '.json';
        file_put_contents($path, json_encode($data));
    }

    private function cleanupIfNeeded(): void
    {
        $cleanupFile = $this->storageDir . '/.last_cleanup';
        if (file_exists($cleanupFile)) {
            $lastCleanup = (int) file_get_contents($cleanupFile);
            if (time() - $lastCleanup < self::CLEANUP_INTERVAL) return;
        }
        file_put_contents($cleanupFile, (string) time());

        $dir = opendir($this->storageDir);
        if (!$dir) return;

        $now = time();
        $checked = 0;
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.last_cleanup') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;

            $checked++;
            $filePath = $this->storageDir . '/' . $file;
            $raw = file_get_contents($filePath);
            $data = json_decode($raw, true);
            if ($data && ($data['reset_at'] ?? 0) < $now) {
                @unlink($filePath);
            }

            if ($checked >= self::CLEANUP_BATCH_SIZE) {
                break;
            }
        }
        closedir($dir);
    }

    public static function flushMemory(): void
    {
        self::$memoryCache = [];
    }
}
