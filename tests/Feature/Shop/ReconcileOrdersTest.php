<?php

use App\Mail\Shop\NewOrderForOffice;
use App\Mail\Shop\OrderConfirmed;
use App\Mail\Shop\WebhookLooksBroken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakePaymentGateway;

/**
 * The hourly check on orders still waiting for Stripe's webhook, with Stripe
 * stood in for by the fake gateway.
 */
beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
    Mail::fake();
    Notification::fake();
});

/**
 * A pending order sent to Stripe the given number of minutes ago, and what
 * Stripe has to say about it.
 *
 * @param  array<string, mixed>  $session
 */
function waitingOrder(array $session = ['status' => 'complete', 'payment_status' => 'paid'], int $minutesOld = 90): Order
{
    $order = Order::factory()->create(['created_at' => now()->subMinutes($minutesOld)]);

    test()->gateway->sessionsAtStripe[$order->stripe_checkout_session_id] = [
        'id' => $order->stripe_checkout_session_id,
        'metadata' => ['order_id' => (string) $order->id],
        'customer_details' => ['email' => 'reader@example.com', 'name' => 'Sam Reader'],
        ...$session,
    ];

    return $order;
}

test('an order paid for without the webhook is marked paid, as the webhook would have', function () {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = waitingOrder();
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->email)->toBe('reader@example.com')
        ->and($order->notes)->toContain('Marked paid by the hourly check')
        ->and($variant->refresh()->stock)->toBe(3);

    Mail::assertQueued(OrderConfirmed::class);
    Mail::assertQueued(NewOrderForOffice::class);
});

test('the office is told, without going through the queue, when the webhook was missed', function () {
    $order = waitingOrder();

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    Mail::assertSent(WebhookLooksBroken::class, fn (WebhookLooksBroken $mail): bool => $mail->orders->contains($order)
        && $mail->hasTo(config('shop.notify.to')));
});

test('an order whose session lapsed is cancelled, with nothing to tell the office', function () {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = waitingOrder(['status' => 'expired', 'payment_status' => 'unpaid']);
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->notes)->toContain('Cancelled by the hourly check')
        ->and($variant->refresh()->stock)->toBe(5);

    Mail::assertNothingOutgoing();
});

test('an order Stripe has not settled is left waiting', function (array $session) {
    $order = waitingOrder($session);

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);

    Mail::assertNothingOutgoing();
})->with([
    'still on the payment page' => [['status' => 'open', 'payment_status' => 'unpaid']],
    'a slower payment still to clear' => [['status' => 'complete', 'payment_status' => 'unpaid']],
]);

test('only orders between an hour and a week old are asked about', function () {
    $tooNew = waitingOrder(minutesOld: 30);
    $inTime = waitingOrder(['status' => 'open', 'payment_status' => 'unpaid'], minutesOld: 61);
    $tooOld = waitingOrder(minutesOld: 8 * 24 * 60);

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    expect($this->gateway->sessionsAskedAbout)->toBe([$inTime->stripe_checkout_session_id])
        ->and($tooNew->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($tooOld->refresh()->status)->toBe(OrderStatus::Pending);
});

test('an order the webhook has already settled is not asked about, or told about twice', function () {
    Order::factory()->paid()->create(['created_at' => now()->subMinutes(90)]);
    waitingOrder();

    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();
    $this->artisan('shop:reconcile-orders --commit')->assertSuccessful();

    expect($this->gateway->sessionsAskedAbout)->toHaveCount(1);

    Mail::assertSent(WebhookLooksBroken::class, 1);
    Mail::assertQueued(OrderConfirmed::class, 1);
});

test('an order Stripe cannot answer about is reported, and the rest still get checked', function () {
    Exceptions::fake();

    $unknown = Order::factory()->create(['created_at' => now()->subMinutes(120)]);
    $paid = waitingOrder();

    $this->artisan('shop:reconcile-orders --commit')->assertFailed();

    expect($unknown->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($paid->refresh()->status)->toBe(OrderStatus::Paid);

    Exceptions::assertReported(RuntimeException::class);
    Mail::assertSent(WebhookLooksBroken::class);
});

test('without --commit it only says what Stripe answered', function () {
    $order = waitingOrder();

    $this->artisan('shop:reconcile-orders')
        ->expectsOutputToContain($order->reference.': Stripe says complete, paid.')
        ->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);

    Mail::assertNothingOutgoing();
});

test('the check is on the schedule, committing', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('shop:reconcile-orders --commit')
        ->assertSuccessful();
});

test('the email says which orders and where to look', function () {
    $order = Order::factory()->paid()->create(['name' => 'Sam Reader', 'total' => 2800]);

    (new WebhookLooksBroken(new Collection([$order])))
        ->assertHasSubject("Stripe's webhook looks broken: 1 paid order missed")
        ->assertSeeInHtml((string) $order->reference)
        ->assertSeeInHtml('/resources/orders/'.$order->id)
        ->assertSeeInHtml('£28.00')
        ->assertSeeInHtml('/stripe/webhook');
});
