<?php
namespace Phoenix\WebSocket;

final class UploadHandler
{
    private const UPLOAD_DIR = __DIR__ . '/../../storage/uploads';
    private const MAX_SIZE = 25 * 1024 * 1024;
    private const ALLOWED_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm',
        'application/pdf', 'text/plain'
    ];

    public static function init(): void
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }

    public static function handle(string $b64data, string $filename, string $mime, string $userName): ?array
    {
        if (strlen($b64data) > self::MAX_SIZE * 1.37) return null;
        $data = base64_decode($b64data, true);
        if ($data === false) return null;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($data);
        if (!in_array($detected, self::ALLOWED_TYPES) || $detected !== $mime) return null;
        $ext = match($detected) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'video/mp4' => '.mp4',
            'video/webm' => '.webm',
            'application/pdf' => '.pdf',
            'text/plain' => '.txt',
            default => '.bin'
        };
        $safeName = bin2hex(random_bytes(16)) . $ext;
        $path = self::UPLOAD_DIR . '/' . $safeName;
        if (file_put_contents($path, $data) === false) return null;
        chmod($path, 0644);
        return [
            'url' => '/storage/uploads/' . $safeName,
            'name' => $filename,
            'size' => strlen($data),
            'mime' => $detected,
            'user' => $userName
        ];
    }
}
