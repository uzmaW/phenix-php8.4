<?php
namespace Phoenix\Notifications;

final class FcmService
{
    private const FCM_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_SEND_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const SCOPES = 'https://www.googleapis.com/auth/firebase.messaging';

    private string $projectId;
    private string $serviceAccountJson;
    private ?string $cachedAccessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(string $serviceAccountJson, ?string $projectId = null)
    {
        if (!is_file($serviceAccountJson)) {
            throw new \RuntimeException("Service account file not found: {$serviceAccountJson}");
        }

        $this->serviceAccountJson = $serviceAccountJson;
        $credentials = $this->loadCredentials();
        $this->projectId = $projectId ?? $credentials['project_id'] ?? '';
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $this->sanitizeData($data);
        }

        return $this->send($message);
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $this->sanitizeData($data);
        }

        return $this->send($message);
    }

    public function sendMulticast(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($tokens as $token) {
            $results[] = $this->sendToToken($token, $title, $body, $data);
        }
        return $results;
    }

    public function sendConditional(array $conditions, string $title, string $body, array $data = []): array
    {
        $message = [
            'message' => [
                'condition' => implode(' && ', $conditions),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $this->sanitizeData($data);
        }

        return $this->send($message);
    }

    private function send(array $message): array
    {
        $accessToken = $this->getAccessToken();

        $url = sprintf(self::FCM_SEND_URL, $this->projectId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("FCM request failed: {$error}");
        }

        $body = json_decode($response, true);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => $body['error']['message'] ?? "HTTP {$httpCode}",
                'status' => $httpCode,
            ];
        }

        return [
            'success' => true,
            'message_id' => $body['name'] ?? '',
        ];
    }

    private function getAccessToken(): string
    {
        if ($this->cachedAccessToken && time() < $this->tokenExpiresAt - 60) {
            return $this->cachedAccessToken;
        }

        $credentials = $this->loadCredentials();
        $jwt = $this->generateJwt($credentials);

        $ch = curl_init(self::FCM_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Token exchange failed: {$error}");
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            throw new \RuntimeException('Failed to obtain access token: ' . ($data['error_description'] ?? 'Unknown error'));
        }

        $this->cachedAccessToken = $data['access_token'];
        $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 3600);

        return $this->cachedAccessToken;
    }

    private function generateJwt(array $credentials): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $now = time();
        $claimSet = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPES,
            'aud' => self::FCM_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $payload = $this->base64UrlEncode(json_encode($claimSet));

        $signatureInput = "{$header}.{$payload}";

        $key = openssl_pkey_get_private($credentials['private_key']);
        if (!$key) {
            throw new \RuntimeException('Failed to load private key from service account');
        }

        openssl_sign($signatureInput, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($key);

        $encodedSignature = $this->base64UrlEncode($signature);

        return "{$header}.{$payload}.{$encodedSignature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = (string) $value;
        }
        return $sanitized;
    }

    private function loadCredentials(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $json = file_get_contents($this->serviceAccountJson);
        $cached = json_decode($json, true);

        if (!$cached) {
            throw new \RuntimeException('Invalid service account JSON file');
        }

        return $cached;
    }
}
