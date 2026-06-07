<?php
namespace Phoenix\Core;

final class Idempotency
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/phoenix_idempotency';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function execute(string $key, callable $operation, int $ttl = 3600): mixed
    {
        $lockPath = $this->storageDir . '/' . md5($key) . '.lock';
        $resultPath = $this->storageDir . '/' . md5($key) . '.result';

        if (file_exists($resultPath)) {
            $data = json_decode(file_get_contents($resultPath), true);
            if ($data && ($data['expires'] ?? 0) > time()) {
                return $data['result'];
            }
        }

        if (file_exists($lockPath)) {
            $lockTime = (int)file_get_contents($lockPath);
            if (time() - $lockTime < 30) {
                throw new \RuntimeException('Operation already in progress');
            }
        }

        file_put_contents($lockPath, (string)time());

        try {
            $result = $operation();
            file_put_contents($resultPath, json_encode([
                'result' => $result,
                'expires' => time() + $ttl,
            ]));
            return $result;
        } finally {
            @unlink($lockPath);
        }
    }

    public function reset(string $key): void
    {
        $lockPath = $this->storageDir . '/' . md5($key) . '.lock';
        $resultPath = $this->storageDir . '/' . md5($key) . '.result';
        @unlink($lockPath);
        @unlink($resultPath);
    }
}
