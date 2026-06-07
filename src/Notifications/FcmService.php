<?php
namespace Phoenix\Notifications;

final class FcmService
{
    private array $sent = [];

    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $message = [
            'token' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sent_at' => time(),
        ];
        $this->sent[] = $message;
        return ['success' => true, 'message_id' => bin2hex(random_bytes(8))];
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        $message = [
            'topic' => $topic,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sent_at' => time(),
        ];
        $this->sent[] = $message;
        return ['success' => true, 'message_id' => bin2hex(random_bytes(8))];
    }

    public function sendMulticast(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($tokens as $token) {
            $results[] = $this->sendToToken($token, $title, $body, $data);
        }
        return $results;
    }

    public function getSentMessages(): array
    {
        return $this->sent;
    }
}
