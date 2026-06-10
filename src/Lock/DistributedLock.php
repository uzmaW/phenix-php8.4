<?php

namespace Phoenix\Lock;

final class DistributedLock
{
    private string $lockDir;

    public function __construct(?string $lockDir = null)
    {
        $this->lockDir = $lockDir ?? sys_get_temp_dir() . '/phoenix_locks';
        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0o755, true);
        }
    }

    public function acquire(string $key, int $ttlSeconds = 30): bool
    {
        $lockFile = $this->lockDir . '/' . md5($key) . '.lock';
        if (file_exists($lockFile)) {
            $lockTime = (int) @file_get_contents($lockFile);
            if (time() - $lockTime < $ttlSeconds) {
                return false;
            }
        }
        file_put_contents($lockFile, (string) time());

        return true;
    }

    public function release(string $key): void
    {
        $lockFile = $this->lockDir . '/' . md5($key) . '.lock';
        @unlink($lockFile);
    }

    public function withLock(string $key, callable $callback, int $ttl = 30): mixed
    {
        if (!$this->acquire($key, $ttl)) {
            throw new \RuntimeException("Could not acquire lock: $key");
        }

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }

    public function isLocked(string $key): bool
    {
        $lockFile = $this->lockDir . '/' . md5($key) . '.lock';

        return file_exists($lockFile);
    }
}
