<?php

namespace Phoenix\Cache;

use Psr\SimpleCache\CacheInterface;

final class FilesystemStore implements CacheInterface
{
    public function __construct(private string $path)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    private function file(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $expires = $ttl ? time() + (int) $ttl : PHP_INT_MAX;
        $data = serialize(['value' => $value, 'expires' => $expires]);
        return file_put_contents($this->file($key), $data) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->file($key);
        return !file_exists($file) || unlink($file);
    }

    public function clear(): bool
    {
        foreach (glob($this->path . '/*.cache') as $file) {
            unlink($file);
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return file_exists($this->file($key)) && $this->get($key) !== null;
    }
}
