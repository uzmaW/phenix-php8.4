<?php

declare(strict_types=1);

namespace App\Services;

use Phoenix\Core\Result;

final class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct(
        ?string $clientId = null,
        ?string $clientSecret = null,
        bool $sandbox = true,
    ) {
        $this->clientId = $clientId ?? (string) ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?: '');
        $this->clientSecret = $clientSecret ?? (string) ($_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET') ?: '');
        $this->baseUrl = $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /** Create a PayPal order (returns approval URL for redirect) */
    public function createOrder(
        float $amount,
        string $currency = 'USD',
        string $returnUrl = '',
        string $cancelUrl = '',
        array $metadata = [],
    ): Result {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return Result::err('PayPal credentials not configured');
        }

        $tokenResult = $this->getAccessToken();
        if ($tokenResult->isErr()) {
            return $tokenResult;
        }

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'custom_id' => $metadata['order_id'] ?? '',
                'description' => $metadata['description'] ?? 'Phoenix Framework Order',
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
                'brand_name' => 'Phoenix Shop',
            ],
        ];

        $result = $this->request('POST', '/v2/checkout/orders', $body);
        if ($result->isErr()) {
            return $result;
        }

        $data = $result->unwrap();
        $approvalUrl = '';
        foreach (($data['links'] ?? []) as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];

                break;
            }
        }

        return Result::ok([
            'order_id' => $data['id'],
            'status' => $data['status'],
            'approval_url' => $approvalUrl,
        ]);
    }

    /** Capture payment for an approved order */
    public function captureOrder(string $orderId): Result
    {
        $tokenResult = $this->getAccessToken();
        if ($tokenResult->isErr()) {
            return $tokenResult;
        }

        return $this->request('POST', "/v2/checkout/orders/{$orderId}/capture");
    }

    /** Get order details */
    public function getOrder(string $orderId): Result
    {
        $tokenResult = $this->getAccessToken();
        if ($tokenResult->isErr()) {
            return $tokenResult;
        }

        return $this->request('GET', "/v2/checkout/orders/{$orderId}");
    }

    private function getAccessToken(): Result
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiry) {
            return Result::ok($this->accessToken);
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return Result::err('PayPal auth failed: ' . $error);
        }
        $data = json_decode($body, true);
        if ($httpCode !== 200) {
            return Result::err($data['error_description'] ?? 'Auth failed');
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + (int) ($data['expires_in'] ?? 3600) - 60;

        return Result::ok($this->accessToken);
    }

    private function request(string $method, string $endpoint, ?array $body = null): Result
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];
        if ($method === 'POST' && $endpoint !== '/v1/oauth2/token') {
            $headers[] = 'PayPal-Request-Id: ' . bin2hex(random_bytes(16));
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ? json_encode($body) : '{}';
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return Result::err('PayPal request failed: ' . $error);
        }
        $data = json_decode($response, true) ?? [];
        if ($httpCode >= 400) {
            return Result::err($data['message'] ?? $data['error_description'] ?? "HTTP {$httpCode}");
        }

        return Result::ok($data);
    }
}
