<?php

declare(strict_types=1);

/**
 * Business scenario: subscription payments with provider fallback.
 *
 * Mirrors the admin Payments Hub backend (App\Services\StripeService and
 * App\Services\PayPalService) and shows how the framework handles a
 * real checkout lifecycle when a payment provider is unavailable:
 *
 *  - StripeService       first-choice payment provider (Result-returning)
 *  - PayPalService       second-choice provider if Stripe is not configured
 *  - Result monad        every provider call is fallible; no exceptions for business errors
 *  - StateMachineBuilder subscription lifecycle (trial -> active -> past_due / cancelled)
 *    with a guard: a subscription cannot be activated before payment succeeds
 *  - ObservableSubject   observers (provisioning, notifications) react to payment events
 *  - Saga                provisioned entitlements (project slots, API keys) are
 *                        compensated (revoked) if a later step fails
 *  - RateLimiter         checkout endpoint protection against Coinbase-style abuse
 *
 * Run: php examples/business-cases/SubscriptionPayments.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Services\PayPalService;
use App\Services\StripeService;
use Phoenix\Pattern\ObservableSubject;
use Phoenix\Pattern\Observer;
use Phoenix\Pattern\State;
use Phoenix\Pattern\StateMachineBuilder;
use Phoenix\Pattern\Subject;
use Phoenix\RateLimit\RateLimiter;
use Phoenix\Saga\Saga;

// ---------------------------------------------------------------------------
// 1. Payment attempt with provider fallback (degrade, don't crash)
// ---------------------------------------------------------------------------

echo "Checkout: PRO-PLAN $29.00 / month\n";

$stripe = new StripeService(); // no STRIPE_SECRET_KEY in this environment
$paypal = new PayPalService(); // no PAYPAL_CLIENT_ID/SECRET either

$checkout = $stripe->createCheckoutSession(
    [['name' => 'PRO-PLAN subscription', 'amount' => 29.0, 'quantity' => 1]],
    'https://app.example.com/billing/success',
    'https://app.example.com/billing/cancel',
    ['customer' => 'CUST-7'],
);

if ($checkout->isErr()) {
    echo '  Stripe unavailable: ' . (string) $checkout->unwrapErr() . "\n";

    $checkout = $paypal->createOrder(
        29.0,
        'USD',
        'https://app.example.com/billing/paypal/success',
        'https://app.example.com/billing/paypal/cancel',
        ['order_id' => 'ORD-SUB-42'],
    );
    echo $checkout->isErr()
        ? '  PayPal unavailable: ' . (string) $checkout->unwrapErr() . "\n"
        : "  PayPal checkout created\n";
}

echo '  decision: ' . ($checkout->isErr() ? 'fall back to playable demo order' : 'redirect to provider') . "\n";

// Offline/demo fallback keeps the admin flows testable without live credentials.
$paymentCaptured = $checkout->isOk();
echo '  payment captured: ' . ($paymentCaptured ? 'yes' : 'no (demo mode)') . "\n";

// ---------------------------------------------------------------------------
// 2. Subscription lifecycle state machine with a payment guard
// ---------------------------------------------------------------------------

$subject = new ObservableSubject('subscription-lifecycle');
$subject->attach(new class () implements Observer {
    public function update(Subject $subject): void
    {
        $s = $subject->get_state();
        if (is_array($s) && ($s['state'] ?? '') === 'active') {
            printf("  [notify] welcome email sent for %s%s\n", $s['plan'], isset($s['provider']) ? ' (' . $s['provider'] . ')' : '');
        }
        if (is_array($s) && ($s['state'] ?? '') === 'past_due') {
            echo "  [notify] dunning reminder queued\n";
        }
    }
});

$subContext = (object) [
    'plan' => 'PRO-PLAN',
    'provider' => 'demo',
    'paid' => $paymentCaptured,
    'trial_ended' => true,
];

$subscription = StateMachineBuilder::create('subscription-lifecycle')
    ->with_context($subContext)
    ->initial_state('trialing')
    ->state('trialing', fn (State $state) => $state
        ->allow_transition_to('active')
        ->allow_transition_to('cancelled'))
    ->state('active', fn (State $state) => $state
        ->allow_transition_to('past_due')
        ->allow_transition_to('cancelled'))
    ->state('past_due', fn (State $state) => $state
        ->allow_transition_to('active')
        ->allow_transition_to('cancelled')
        ->on_entry(function (object $ctx) use ($subject): void {
            $subject->set_state(['state' => 'past_due', 'plan' => $ctx->plan]);
            $subject->notify();
        }))
    ->state('cancelled')
    // Guard: activation requires a captured payment
    ->transition('trialing', 'active', fn (object $ctx): bool => $ctx->paid, function (object $ctx) use ($subject): void {
        $subject->set_state([
            'state' => 'active',
            'plan' => $ctx->plan,
            'provider' => $ctx->provider,
        ]);
        $subject->notify();
    })
    ->transition('active', 'past_due')
    ->transition('past_due', 'active')
    ->transition('trialing', 'cancelled')
    ->transition('active', 'cancelled')
    ->transition('past_due', 'cancelled')
    ->build();

echo "\nSubscription lifecycle:\n";
echo '  start: trialing' . "\n";

$activate = $subscription->transition_to('active');
echo '  activate without payment: ' . ($activate->isErr() ? 'blocked by payment guard' : 'activated') . "\n";

$subContext->paid = true;
$subContext->provider = $checkout->isOk() ? ($checkout->unwrap()['order_id'] ?? 'demo') : 'demo';
$activated = $subscription->transition_to('active');
echo '  activate after capture: ' . ($activated->isOk() ? 'activated' : 'blocked') . "\n";
echo '  current state: ' . $subscription->get_current_state() . "\n";

$dunning = $subscription->transition_to('past_due'); // later: recurring charge failed
echo "  missed renewal: past_due -> dunning reminder queued\n";
$reactivate = $subscription->transition_to('active'); // card retry succeeded
echo '  renewal retry: ' . ($reactivate->isOk() ? 'active again' : 'blocked') . "\n";
echo '  transition metrics: ' . json_encode($subscription->get_metrics()) . "\n";

// ---------------------------------------------------------------------------
// 3. Saga: provision entitlements with compensation on failure
// ---------------------------------------------------------------------------

echo "\nProvisioning saga:\n";

$provisioned = [];

$saga = new Saga();
$saga->dryRun(false);
$saga->addStep(
    'grant_project_slots',
    function () use (&$provisioned): void {
        $provisioned['project_slots'] = 10;
    },
    function () use (&$provisioned): void {
        unset($provisioned['project_slots']);
        echo '    [compensate] project slots revoked' . "\n";
    },
    'PRO-PLAN grants 10 project slots',
);
$saga->addStep(
    'issue_api_keys',
    function () use (&$provisioned): void {
        $provisioned['api_keys'] = 3;
    },
    function () use (&$provisioned): void {
        unset($provisioned['api_keys']);
        echo '    [compensate] API keys revoked' . "\n";
    },
    'PRO-PLAN grants 3 API keys',
);
$saga->addStep(
    'register_webhook_endpoint',
    static function (): never {
        throw new RuntimeException('webhook registrar unavailable');
    },
    function (): void {
        echo '    [compensate] webhook endpoint removed' . "\n";
    },
    'Register billing webhook (simulated outage)',
);

$executed = $saga->execute();
echo '  outcome: ' . ($executed->isErr() ? 'failed (entitlements rolled back)' : 'all steps completed') . "\n";
echo '  remaining entitlements: ' . (count($provisioned) ? implode(', ', array_keys($provisioned)) : 'none') . "\n";
echo '  audit log entries: ' . count($saga->getAuditLog()) . "\n";

// ---------------------------------------------------------------------------
// 4. Rate limiting on the checkout endpoint
// ---------------------------------------------------------------------------

echo "\nCheckout endpoint protection:\n";

$limiter = new RateLimiter(null, true);
$allowed = 0;
for ($i = 0; $i < 12; $i++) {
    if ($limiter->attempt('checkout:127.0.0.1', 10, 60)) {
        $allowed++;
    }
}
printf(
    "  12 attempts: %d allowed, %d throttled (limit 10/min), %d remaining\n",
    $allowed,
    12 - $allowed,
    $limiter->remaining('checkout:127.0.0.1', 10, 60),
);

echo "\nDone.\n";
