<?php
declare(strict_types=1);
namespace App\Services;
use Phoenix\Core\Result;

final class StripeService {
    private string $apiKey;
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey ?? (string) ($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: '');
    }

    /** Create a Stripe Checkout Session (hosted payment page) */
    public function createCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        string $mode = 'payment'
    ): Result {
        if (empty($this->apiKey)) {
            return Result::err('Stripe API key not configured');
        }

        $params = [
            'mode' => $mode,
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ];

        // Add line items
        foreach ($lineItems as $i => $item) {
            $params["line_items[{$i}][price_data][currency]"] = $item['currency'] ?? 'usd';
            $params["line_items[{$i}][price_data][product_data][name]"] = $item['name'];
            $params["line_items[{$i}][price_data][unit_amount]"] = (int)round(($item['amount'] ?? 0) * 100); // cents
            $params["line_items[{$i}][quantity]"] = $item['quantity'] ?? 1;
        }

        // Add metadata
        foreach ($metadata as $key => $val) {
            $params["metadata[{$key}]"] = $val;
        }

        return $this->request('POST', '/checkout/sessions', $params);
    }

    /** Retrieve a checkout session by ID */
    public function getSession(string $sessionId): Result {
        return $this->request('GET', "/checkout/sessions/{$sessionId}");
    }

    /** Retrieve a payment intent */
    public function getPaymentIntent(string $paymentIntentId): Result {
        return $this->request('GET', "/payment_intents/{$paymentIntentId}");
    }

    /** Verify webhook signature */
    public function verifyWebhook(string $payload, string $sigHeader, string $webhookSecret): Result {
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $val] = explode('=', $part, 2);
            $parts[$key] = $val;
        }
        $timestamp = $parts['t'] ?? '';
        $sig = $parts['v1'] ?? '';
        if (empty($timestamp) || empty($sig)) {
            return Result::err('Invalid webhook signature header');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
        if (!hash_equals($expected, $sig)) {
            return Result::err('Webhook signature mismatch');
        }
        // Timestamp tolerance: 5 minutes
        if (abs(time() - (int)$timestamp) > 300) {
            return Result::err('Webhook timestamp too old');
        }
        return Result::ok(json_decode($payload, true));
    }

    private function request(string $method, string $endpoint, array $params = []): Result {
        $ch = curl_init($this->baseUrl . $endpoint);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->apiKey . ':',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return Result::err('Stripe request failed: ' . $error);
        }
        $data = json_decode($body, true);
        if ($httpCode >= 400) {
            return Result::err($data['error']['message'] ?? "HTTP {$httpCode}");
        }
        return Result::ok($data);
    }
}
