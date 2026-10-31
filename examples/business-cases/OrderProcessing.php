<?php

declare(strict_types=1);

/**
 * Business scenario: order processing pipeline.
 *
 * Demonstrates the rust-like features integrated into Phoenix working
 * together on a realistic domain flow:
 *
 *  - OptimizedVector      order line items (batch inserts, map/filter)
 *  - OptimizedHashMap     customer index and order lookup by id
 *  - OptimizedHashSet     unique SKUs and tag sets (union/intersection)
 *  - StateMachineBuilder  order lifecycle with guard conditions
 *  - ObservableSubject    shipping/invoice observers notified on transitions
 *  - ComplexBuilder       validated construction of Order objects
 *  - Phoenix\Core\Result  fallible operations without exceptions
 *  - AsyncResult          lazy async settlement + invoice generation
 *  - MacroSystem          repository code generation for the orders module
 *
 * Run: php examples/business-cases/OrderProcessing.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use Phoenix\Concurrency\AsyncResult;
use Phoenix\Core\Result;
use Phoenix\Macros\MacroSystem;
use Phoenix\Macros\MacroUtils;
use Phoenix\Collections\OptimizedHashMap;
use Phoenix\Collections\OptimizedHashSet;
use Phoenix\Collections\OptimizedVector;
use Phoenix\Pattern\BaseEvent;
use Phoenix\Pattern\ComplexBuilder;
use Phoenix\Pattern\ObservableSubject;
use Phoenix\Pattern\Observer;
use Phoenix\Pattern\State;
use Phoenix\Pattern\StateMachineBuilder;
use Phoenix\Pattern\Subject;

final class LineItem
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $unit_price,
    ) {}

    public function total(): float
    {
        return $this->quantity * $this->unit_price;
    }
}

final class Order
{
    /** @var OptimizedVector<LineItem> */
    public readonly OptimizedVector $items;

    public function __construct(
        public readonly string $id,
        public readonly string $customer_id,
        array $items,
        public readonly OptimizedHashSet $tags,
    ) {
        $this->items = new OptimizedVector($items);
    }

    public function subtotal(): float
    {
        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += $item->total();
        }

        return $sum;
    }

    public function skus(): OptimizedHashSet
    {
        $skus = new OptimizedHashSet();
        foreach ($this->items as $item) {
            $skus->add($item->sku);
        }

        return $skus;
    }
}

final class OrderShippedEvent extends BaseEvent
{
    protected const EVENT_NAME = 'order.shipped';
}

final class InvoiceGeneratedEvent extends BaseEvent
{
    protected const EVENT_NAME = 'invoice.generated';
}

/** Shipping notifier: observes order state changes */
final class ShippingNotifier implements Observer
{
    public array $notifications = [];

    public function update(Subject $subject): void
    {
        $payload = $subject->get_state();
        if (is_array($payload) && ($payload['state'] ?? null) === 'shipped') {
            $this->notifications[] = sprintf(
                'Shipped order %s via %s',
                $payload['order_id'],
                $payload['carrier'] ?? 'standard',
            );
        }
    }
}

/** Invoice registrar: observes order state changes */
final class InvoiceRegistrar implements Observer
{
    public array $invoices = [];

    public function update(Subject $subject): void
    {
        $payload = $subject->get_state();
        if (is_array($payload) && ($payload['state'] ?? null) === 'paid') {
            $this->invoices[] = [
                'order_id' => $payload['order_id'],
                'amount' => $payload['amount'],
            ];
        }
    }
}

// ---------------------------------------------------------------------------
// 1. Build a validated order with ComplexBuilder
// ---------------------------------------------------------------------------

$orderBuilder = new ComplexBuilder(Order::class);
$orderBuilder->add_step('identity', fn (): array => [
    'id' => 'ORD-1001',
    'customer_id' => 'CUST-7',
]);
$orderBuilder->add_step('items', fn (): array => [
    'items' => [
        new LineItem('SKU-1', 'Mechanical Keyboard', 2, 120.0),
        new LineItem('SKU-2', 'USB-C Cable', 4, 9.5),
        new LineItem('SKU-1', 'Mechanical Keyboard', 1, 120.0),
    ],
]);
$orderBuilder->add_step('tags', fn (): array => [
    'tags' => new OptimizedHashSet(['priority', 'prepaid']),
]);
$orderBuilder->add_post_build_hook(function (Order $order): void {
    // Business invariant: an order must have at least one line item
    if ($order->items->count() === 0) {
        throw new InvalidArgumentException('Order must contain at least one line item');
    }
});

$order = $orderBuilder->build();

echo "Order {$order->id} for customer {$order->customer_id}\n";
echo '  line items: ' . $order->items->count() . ', subtotal: $' . number_format($order->subtotal(), 2) . "\n";
echo '  unique SKUs: ' . implode(', ', $order->skus()->to_array()) . "\n";

// Batch append using the optimized vector API
$order->items->push_batch([
    new LineItem('SKU-9', 'Desk Mat', 1, 25.0),
]);
echo '  after batch push: ' . $order->items->count() . " items, subtotal $" . number_format($order->subtotal(), 2) . "\n";

// Filter with the vector helper: only items over $50 unit price
$bigTickets = $order->items->filter(fn (LineItem $item): bool => $item->unit_price > 50.0);
echo '  high-value SKUs: ' . implode(', ', $bigTickets->map(fn (LineItem $i): string => $i->sku)->to_array()) . "\n";

// ---------------------------------------------------------------------------
// 2. Index orders and customers with OptimizedHashMap
// ---------------------------------------------------------------------------

$orderIndex = new OptimizedHashMap();
$orderIndex->set($order->id, $order);

$customerOrders = new OptimizedHashMap();
$customerOrders->set_many([
    'CUST-7' => ['ORD-1001'],
    'CUST-8' => [],
]);

echo '  order lookup: ' . ($orderIndex->has('ORD-1001') ? 'hit' : 'miss') . "\n";
echo '  orders for CUST-7: ' . count($customerOrders->get('CUST-7')) . "\n";

// ---------------------------------------------------------------------------
// 3. Order lifecycle state machine with observers
// ---------------------------------------------------------------------------

$context = (object) [
    'order_id' => $order->id,
    'amount' => $order->subtotal(),
    'paid' => false,
    'carrier' => 'DHL',
];

$subject = new ObservableSubject('order-lifecycle');
$shipping = new ShippingNotifier();
$invoices = new InvoiceRegistrar();
$subject->attach($shipping);
$subject->attach($invoices);

$transitionLog = [];

$machine = StateMachineBuilder::create('order-lifecycle')
    ->with_context($context)
    ->initial_state('created')
    ->state('created', fn (State $state) => $state
        ->allow_transition_to('paid')
        ->allow_transition_to('cancelled')
        ->on_exit(function (object $ctx) use (&$transitionLog): void {
            $transitionLog[] = 'created -> left';
        })
    )
    ->state('paid', fn (State $state) => $state
        ->allow_transition_to('shipped')
        ->allow_transition_to('refunded')
        ->on_entry(function (object $ctx) use ($subject): void {
            $subject->set_state([
                'state' => 'paid',
                'order_id' => $ctx->order_id,
                'amount' => $ctx->amount,
            ]);
            $subject->notify();
        })
    )
    ->state('shipped', fn (State $state) => $state->on_entry(function (object $ctx) use ($subject): void {
        $subject->set_state([
            'state' => 'shipped',
            'order_id' => $ctx->order_id,
            'carrier' => $ctx->carrier,
        ]);
        $subject->notify();
    }))
    ->state('cancelled')
    ->state('refunded')
    // Guard: payment must have been captured before the order can be marked paid
    ->transition('created', 'paid', fn (object $ctx): bool => $ctx->paid === true, function (object $ctx) use (&$transitionLog): void {
        $transitionLog[] = sprintf('payment captured for %s ($%.2f)', $ctx->order_id, $ctx->amount);
    })
    ->transition('created', 'cancelled')
    ->transition('paid', 'shipped', null, function (object $ctx) use (&$transitionLog): void {
        $transitionLog[] = sprintf('dispatched via %s', $ctx->carrier);
    })
    ->transition('paid', 'refunded')
    ->build();

echo "\nLifecycle:\n";
echo '  start: ' . $machine->get_current_state() . "\n";

// Guard blocks premature payment
$denied = $machine->transition_to('paid');
echo '  pay before capture: ' . ($denied->isErr() ? 'denied (' . $denied->unwrapErr()->get_message() . ')' : 'allowed') . "\n";

// Capture payment, then retry (guard now passes)
$context->paid = true;
$paid = $machine->transition_to('paid');
echo '  pay after capture: ' . ($paid->isOk() ? 'accepted' : 'denied') . "\n";

$shipped = $machine->transition_to('shipped');
echo '  ship: ' . ($shipped->isOk() ? 'accepted' : 'denied') . "\n";
echo '  final state: ' . $machine->get_current_state() . "\n";

echo '  invoices registered: ' . count($invoices->invoices) . "\n";
echo '  shipping notifications: ' . implode(' | ', $shipping->notifications) . "\n";
echo '  transition log: ' . implode(' | ', $transitionLog) . "\n";
echo '  state machine metrics: ' . json_encode($machine->get_metrics()) . "\n";

// ---------------------------------------------------------------------------
// 4. Async settlement + invoice generation with AsyncResult
// ---------------------------------------------------------------------------

echo "\nAsync settlement:\n";

$paymentFuture = AsyncResult::defer(function () use ($context): float {
    // Simulated payment gateway call
    return (float) $context->amount;
});

$invoiceFuture = $paymentFuture->then(
    fn (float $amount): float => round($amount * 0.19, 2), // VAT
);

$ledgerFuture = AsyncResult::all([$paymentFuture, $invoiceFuture]);

echo '  payment settled: $' . number_format($paymentFuture->get(), 2) . "\n";
echo '  invoice (VAT): $' . number_format($invoiceFuture->get(), 2) . "\n";

[$gross, $vat] = $ledgerFuture->get();
printf("  ledger entry: gross \$%.2f, vat \$%.2f, net \$%.2f\n", $gross, $vat, $gross - $vat);

// Failure path stays in the Result/Either lane instead of throwing.
// A shipped order cannot be refunded directly - the state machine rejects it.
$refund = $machine->transition_to('refunded');
echo '  refund while shipped: ' . ($refund->isErr() ? 'rejected (' . $refund->unwrapErr()->get_message() . ')' : 'ok') . "\n";

$invalid = $machine->transition_to('created');
echo '  back to created: ' . ($invalid->isErr() ? 'rejected by state machine' : 'unexpected') . "\n";

// ---------------------------------------------------------------------------
// 5. Set algebra over SKUs and tags
// ---------------------------------------------------------------------------

echo "\nSet algebra:\n";

$warehouseA = new OptimizedHashSet(['SKU-1', 'SKU-2', 'SKU-9']);
$warehouseB = new OptimizedHashSet(['SKU-2', 'SKU-9', 'SKU-77']);

echo '  A union B: ' . implode(', ', $warehouseA->union($warehouseB)->to_array()) . "\n";
echo '  A intersect B: ' . implode(', ', $warehouseA->intersection($warehouseB)->to_array()) . "\n";
echo '  A difference B: ' . implode(', ', $warehouseA->difference($warehouseB)->to_array()) . "\n";

$promoted = $order->tags->union(new OptimizedHashSet(['shipped']));
echo '  order tags after shipping: ' . implode(', ', $promoted->to_array()) . "\n";

// ---------------------------------------------------------------------------
// 6. Macro-generated repository scaffold
// ---------------------------------------------------------------------------

echo "\nMacro-generated orders repository:\n";

MacroUtils::register_builtin_macros();
$expansion = MacroSystem::get_instance()->expand_macro('repository', [
    'Order', // entity class name
    'App\\Repositories\\OrderRepositoryInterface',
]);

if ($expansion->has_errors()) {
    echo '  codegen failed: ' . implode(', ', $expansion->errors) . "\n";
} else {
    foreach (array_slice($expansion->code, 0, 3) as $line) {
        echo '  ' . trim($line) . "\n";
    }
    echo '  ... (' . count($expansion->code) . " lines generated)\n";
}

echo "\nDone.\n";
