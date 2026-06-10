<?php

namespace Phoenix\Cache;

use Psr\SimpleCache\CacheInterface;

final class FilesystemStore implements CacheInterface
{
    private array $memoryCache = [];
    private array $negativeCache = [];
    private string $path;
    private bool $useMemory;

    public function __construct(string $path, bool $useMemory = true)
    {
        $this->path = $path;
        $this->useMemory = $useMemory && function_exists('apcu_fetch') && (bool) ini_get('apc.enabled');

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
        if ($this->useMemory && isset($this->memoryCache[$key])) {
            $cached = $this->memoryCache[$key];
            if ($cached['expires'] >= time()) {
                return $cached['value'];
            }
            unset($this->memoryCache[$key]);
        }

        if ($this->useMemory && isset($this->negativeCache[$key])) {
            if ($this->negativeCache[$key] >= time()) {
                return $default;
            }
            unset($this->negativeCache[$key]);
        }

        $file = $this->file($key);
        if (!file_exists($file)) {
            if ($this->useMemory) {
                $this->negativeCache[$key] = time() + 60;
            }
            return $default;
        }

        $raw = file_get_contents($file);
        $data = json_decode($raw, true);

        if (!$data || ($data['expires'] ?? 0) < time()) {
            @unlink($file);
            if ($this->useMemory) {
                $this->negativeCache[$key] = time() + 60;
            }
            return $default;
        }

        if ($this->useMemory) {
            $this->memoryCache[$key] = $data;
            unset($this->negativeCache[$key]);
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $expires = $ttl ? time() + (int) $ttl : PHP_INT_MAX;
        $data = ['value' => $value, 'expires' => $expires];
        $json = json_encode($data);

        if ($this->useMemory) {
            $this->memoryCache[$key] = $data;
            unset($this->negativeCache[$key]);
        }

        return file_put_contents($this->file($key), $json) !== false;
    }

    public function delete(string $key): bool
    {
        if ($this->useMemory) {
            unset($this->memoryCache[$key], $this->negativeCache[$key]);
        }

        $file = $this->file($key);
        return !file_exists($file) || unlink($file);
    }

    public function clear(): bool
    {
        if ($this->useMemory) {
            $this->memoryCache = [];
            $this->negativeCache = [];
        }

        $dir = opendir($this->path);
        if (!$dir) return false;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                @unlink($this->path . '/' . $file);
            }
        }
        closedir($dir);
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
        if ($this->useMemory && isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key]['expires'] >= time();
        }

        if ($this->useMemory && isset($this->negativeCache[$key])) {
            if ($this->negativeCache[$key] >= time()) {
                return false;
            }
            unset($this->negativeCache[$key]);
        }

        $file = $this->file($key);
        if (!file_exists($file)) {
            if ($this->useMemory) {
                $this->negativeCache[$key] = time() + 60;
            }
            return false;
        }

        $raw = file_get_contents($file);
        $data = json_decode($raw, true);

        if (!$data || ($data['expires'] ?? 0) < time()) {
            @unlink($file);
            if ($this->useMemory) {
                $this->negativeCache[$key] = time() + 60;
            }
            return false;
        }

        if ($this->useMemory) {
            $this->memoryCache[$key] = $data;
        }

        return true;
    }

    public function flushMemory(): void
    {
        $this->memoryCache = [];
        $this->negativeCache = [];
    }
}
