<?php
namespace Phoenix\WebSocket;

final class SessionManager
{
    private string $sessionDir;
    private const TTL = 86400;

    public function __construct(?string $sessionDir = null)
    {
        $this->sessionDir = $sessionDir ?? sys_get_temp_dir() . '/phoenix_ws_sessions';
        if (!is_dir($this->sessionDir)) {
            mkdir($this->sessionDir, 0755, true);
        }
    }

    public function create(string $userId, string $name): string
    {
        $token = bin2hex(random_bytes(32));
        $session = [
            'user_id' => $userId,
            'name' => $name,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created' => time(),
            'expires' => time() + self::TTL
        ];
        file_put_contents($this->sessionDir . '/' . $token . '.json', json_encode($session));
        return $token;
    }

    public function validate(string $token): ?array
    {
        $path = $this->sessionDir . '/' . $token . '.json';
        if (!file_exists($path)) return null;
        $data = json_decode(file_get_contents($path), true);
        if (!$data || ($data['expires'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }
        return $data;
    }

    public function destroy(string $token): void
    {
        $path = $this->sessionDir . '/' . $token . '.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
