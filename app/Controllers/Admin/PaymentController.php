<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Services\StripeService;
use App\Services\PayPalService;
use Phoenix\View\Factory;

class PaymentController {
    public function index(): string {
        return Factory::make('admin/layouts/admin', [
            'title' => 'Payment Integrations',
            'content' => Factory::make('admin/payments/index', [
                'stripeConfigured' => !empty($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY')),
                'paypalConfigured' => !empty($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID')),
            ])->render(),
            'currentPage' => 'payments',
        ])->render();
    }

    public function stripeCheckout(): string {
        $stripe = new StripeService();
        $baseUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000');

        $result = $stripe->createCheckoutSession(
            lineItems: [[
                'name' => 'Phoenix Framework License',
                'amount' => 99.00,
                'currency' => 'usd',
                'quantity' => 1,
            ]],
            successUrl: $baseUrl . '/admin/payments/stripe/success',
            cancelUrl: $baseUrl . '/admin/payments/stripe/cancel',
            metadata: ['source' => 'admin_demo']
        );

        if ($result->isErr()) {
            $_SESSION['payment_error'] = 'Stripe: ' . $result->unwrapErr();
            header('Location: /admin/payments');
            exit;
        }

        $session = $result->unwrap();
        header('Location: ' . $session['url']);
        exit;
    }

    public function stripeSuccess(): string {
        $sessionId = $_GET['session_id'] ?? '';
        return Factory::make('admin/layouts/admin', [
            'title' => 'Payment Successful',
            'content' => Factory::make('admin/payments/stripe_success', ['sessionId' => $sessionId])->render(),
            'currentPage' => 'payments',
        ])->render();
    }

    public function stripeCancel(): string {
        return Factory::make('admin/layouts/admin', [
            'title' => 'Payment Cancelled',
            'content' => Factory::make('admin/payments/stripe_cancel', [])->render(),
            'currentPage' => 'payments',
        ])->render();
    }

    public function stripeWebhook(): void {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?? '';

        if (empty($webhookSecret)) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook secret not configured']);
            exit;
        }

        $stripe = new StripeService();
        $result = $stripe->verifyWebhook($payload, $sigHeader, $webhookSecret);

        if ($result->isErr()) {
            http_response_code(400);
            echo json_encode(['error' => $result->unwrapErr()]);
            exit;
        }

        $event = $result->unwrap();
        // Handle event types
        match($event['type'] ?? '') {
            'checkout.session.completed' => error_log('Stripe: checkout completed ' . json_encode($event['data']['object']['id'] ?? '')),
            'payment_intent.succeeded' => error_log('Stripe: payment succeeded'),
            default => null,
        };

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    public function paypalCheckout(): string {
        $paypal = new PayPalService(sandbox: true);
        $baseUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000');

        $result = $paypal->createOrder(
            amount: 99.00,
            currency: 'USD',
            returnUrl: $baseUrl . '/admin/payments/paypal/success',
            cancelUrl: $baseUrl . '/admin/payments/paypal/cancel',
            metadata: ['order_id' => 'DEMO-' . time(), 'description' => 'Phoenix Framework License']
        );

        if ($result->isErr()) {
            $_SESSION['payment_error'] = 'PayPal: ' . $result->unwrapErr();
            header('Location: /admin/payments');
            exit;
        }

        $order = $result->unwrap();
        if (!empty($order['approval_url'])) {
            header('Location: ' . $order['approval_url']);
            exit;
        }

        $_SESSION['payment_error'] = 'PayPal: No approval URL returned';
        header('Location: /admin/payments');
        exit;
    }

    public function paypalSuccess(): string {
        $orderId  = $_GET['token'] ?? '';
        $captured = null;

        // Only attempt capture when real credentials are configured
        $hasCredentials = !empty($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID'));
        if ($orderId && $hasCredentials) {
            $paypal   = new PayPalService(sandbox: true);
            $result   = $paypal->captureOrder($orderId);
            $captured = $result->isOk() ? $result->unwrap() : null;
        } elseif ($orderId) {
            // Demo mode: show synthetic captured state
            $captured = ['status' => 'COMPLETED (demo)'];
        }

        return Factory::make('admin/layouts/admin', [
            'title'   => 'PayPal Payment Successful',
            'content' => Factory::make('admin/payments/paypal_success', [
                'orderId'  => $orderId,
                'captured' => $captured,
            ])->render(),
            'currentPage' => 'payments',
        ])->render();
    }

    public function paypalCancel(): string {
        return Factory::make('admin/layouts/admin', [
            'title' => 'PayPal Payment Cancelled',
            'content' => Factory::make('admin/payments/paypal_cancel', [])->render(),
            'currentPage' => 'payments',
        ])->render();
    }
}
