<?php

use App\Mail\Shop\NewOrderForOffice;
use App\Mail\Shop\OrderConfirmed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Nova\Notifications\NovaNotification;
use Tests\Fakes\FakePaymentGateway;

/**
 * Stripe's webhooks, as they arrive at Cashier's endpoint: an order paid,
 * lapsed or refunded. The webhook secret is blanked for tests, so Cashier
 * takes them without a signature.
 */
beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
    Mail::fake();
    Notification::fake();
});

/**
 * Send Stripe's event to the webhook.
 *
 * @param  array<string, mixed>  $object
 */
function stripeEvent(string $type, array $object): void
{
    test()->postJson('/stripe/webhook', [
        'id' => 'evt_test_'.uniqid(),
        'type' => $type,
        'data' => ['object' => $object],
    ])->assertOk();
}

/**
 * A completed Checkout session for an order, as Stripe sends it.
 *
 * @return array<string, mixed>
 */
function completedSession(Order $order, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $order->stripe_checkout_session_id,
        'object' => 'checkout.session',
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_123',
        'metadata' => ['order_id' => (string) $order->id, 'reference' => $order->reference],
        'customer_details' => [
            'email' => 'reader@example.com',
            'name' => 'Sam Reader',
            'phone' => '+447700900123',
            'address' => ['line1' => '1 High Street', 'line2' => null, 'city' => 'Stroud', 'state' => 'Glos', 'postal_code' => 'GL5 1AA', 'country' => 'GB'],
        ],
        'collected_information' => [
            'shipping_details' => [
                'name' => 'Sam Reader',
                'address' => ['line1' => '2 Lansdown', 'line2' => null, 'city' => 'Stroud', 'state' => null, 'postal_code' => 'GL5 1BB', 'country' => 'GB'],
            ],
        ],
    ], $overrides);
}

/**
 * A pending order for some of a variant.
 */
function pendingOrder(ProductVariant $variant, int $quantity = 1): Order
{
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);

    return $order;
}

test('a completed, paid session marks the order paid with what Stripe collected', function () {
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order));

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->email)->toBe('reader@example.com')
        ->and($order->name)->toBe('Sam Reader')
        ->and($order->phone)->toBe('+447700900123')
        ->and($order->stripe_payment_intent_id)->toBe('pi_test_123')
        ->and($order->receipt_url)->toBe('https://pay.stripe.test/receipts/pi_test_123')
        ->and(Order::addressLines($order->shipping_address))->toBe(['Sam Reader', '2 Lansdown', 'Stroud', 'GL5 1BB', 'GB'])
        ->and(Order::addressLines($order->billing_address))->toBe(['Sam Reader', '1 High Street', 'Stroud', 'Glos', 'GL5 1AA', 'GB']);
});

test('a paid order takes its items off the shelf', function () {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = pendingOrder($variant, 2);

    stripeEvent('checkout.session.completed', completedSession($order));

    expect($variant->fresh()->stock)->toBe(3)
        ->and($order->fresh()->oversold)->toBeFalse();
});

test('an order paid for after the last one went is flagged as oversold', function () {
    $variant = ProductVariant::factory()->create(['stock' => 1]);
    $order = pendingOrder($variant, 2);

    stripeEvent('checkout.session.completed', completedSession($order));

    expect($variant->fresh()->stock)->toBe(0)
        ->and($order->fresh()->oversold)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a paid order tells the customer, the orders inbox and the office', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $reader = User::factory()->create(['email' => 'someone@example.com']);
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order));

    Mail::assertQueued(OrderConfirmed::class, fn (OrderConfirmed $mail) => $mail->hasTo('reader@example.com'));
    Mail::assertQueued(NewOrderForOffice::class, fn (NewOrderForOffice $mail) => $mail->hasTo('orders@goodonpaper.info')
        && $mail->hasCc('robin@ultrasimplified.com'));
    Notification::assertSentTo($staff, NovaNotification::class);
    Notification::assertNotSentTo($reader, NovaNotification::class);
});

test('Stripe sending the same webhook twice changes nothing the second time', function () {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = pendingOrder($variant, 2);

    stripeEvent('checkout.session.completed', completedSession($order));
    stripeEvent('checkout.session.completed', completedSession($order));

    expect($variant->fresh()->stock)->toBe(3);
    Mail::assertQueued(OrderConfirmed::class, 1);
});

test('a session completed without the money in yet leaves the order pending', function () {
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order, ['payment_status' => 'unpaid']));

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a session that is not one of ours is passed over', function () {
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order, ['metadata' => ['order_id' => null]]));
    stripeEvent('checkout.session.completed', ['id' => 'cs_live_somebody_else', 'payment_status' => 'paid']);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    Mail::assertNothingQueued();
});

test('a session for one order cannot pay for another', function () {
    $order = pendingOrder(ProductVariant::factory()->create());
    $other = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order, ['metadata' => ['order_id' => (string) $other->id]]));

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($other->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a session that lapses unpaid cancels its order', function () {
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.expired', completedSession($order, ['payment_status' => 'unpaid']));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a lapsed session arriving after the payment leaves the order paid', function () {
    $order = pendingOrder(ProductVariant::factory()->create());

    stripeEvent('checkout.session.completed', completedSession($order));
    stripeEvent('checkout.session.expired', completedSession($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a full refund before the order leaves cancels it and puts the stock back', function () {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = pendingOrder($variant, 2);
    stripeEvent('checkout.session.completed', completedSession($order));

    stripeEvent('charge.refunded', ['payment_intent' => 'pi_test_123', 'amount' => $order->total, 'amount_refunded' => $order->total]);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->refunded_amount)->toBe($order->total)
        ->and($variant->fresh()->stock)->toBe(5);
});

test('a part refund only records what went back', function () {
    $order = pendingOrder(ProductVariant::factory()->create());
    stripeEvent('checkout.session.completed', completedSession($order));

    stripeEvent('charge.refunded', ['payment_intent' => 'pi_test_123', 'amount' => $order->total, 'amount_refunded' => 500]);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->refunded_amount)->toBe(500);
});

test('a full refund after posting marks the order refunded', function () {
    $order = Order::factory()->paid()->create(['stripe_payment_intent_id' => 'pi_test_posted']);
    $order->transitionTo(OrderStatus::Posted);

    stripeEvent('charge.refunded', ['payment_intent' => 'pi_test_posted', 'amount' => $order->total, 'amount_refunded' => $order->total]);

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->refunded_at)->not->toBeNull();
});
