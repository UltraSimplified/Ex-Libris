<?php

use App\Mail\Shop\OrderPosted;
use App\Mail\Shop\OrderReadyForCollection;
use App\Mail\Shop\OrderRefunded;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shop\OrderFulfilment;
use App\Services\Shop\OrderRefunds;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakePaymentGateway;

/**
 * The office moving orders on and giving money back, through the services
 * and through the Office's actions, lenses and metrics.
 */
beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
    Mail::fake();
});

function officeUser(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com', 'name' => 'Robin']);
}

/**
 * Run a Nova action on some orders as the office.
 *
 * @param  list<Order>  $orders
 * @param  array<string, mixed>  $fields
 */
function runOrderAction(string $action, array $orders, array $fields = [], string $path = '/nova-api/orders/action'): TestResponse
{
    return test()->actingAs(officeUser())->postJson($path.'?action='.$action, [
        'resources' => collect($orders)->pluck('id')->all(),
        ...$fields,
    ]);
}

test('posting an order stamps it, keeps the tracking number and tells the customer', function () {
    $order = Order::factory()->paid()->create();

    app(OrderFulfilment::class)->post($order, ' RM123456789GB ');

    expect($order->fresh()->status)->toBe(OrderStatus::Posted)
        ->and($order->fresh()->posted_at)->not->toBeNull()
        ->and($order->fresh()->tracking_number)->toBe('RM123456789GB');
    Mail::assertQueued(OrderPosted::class, fn (OrderPosted $mail) => $mail->hasTo($order->email));
});

test('a collection order marked ready tells the customer where to come', function () {
    $order = Order::factory()->paid()->collection()->create();

    app(OrderFulfilment::class)->ready($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Ready);
    Mail::assertQueued(OrderReadyForCollection::class);
});

test('handing an order to someone else is noted', function () {
    $order = Order::factory()->paid()->collection()->create();
    app(OrderFulfilment::class)->ready($order);

    app(OrderFulfilment::class)->collect($order, 'Their neighbour, Pat');

    expect($order->fresh()->status)->toBe(OrderStatus::Collected)
        ->and($order->fresh()->notes)->toContain('Collected by Their neighbour, Pat.');
});

test('cancelling an order refunds it in full at Stripe, returns the stock and tells the customer', function () {
    $variant = ProductVariant::factory()->create(['stock' => 3]);
    $order = Order::factory()->paid()->create(['total' => 2800]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id, 'quantity' => 2]);

    app(OrderRefunds::class)->cancel($order, officeUser());

    expect($this->gateway->refunds)->toBe([[
        'payment_intent' => $order->stripe_payment_intent_id,
        'amount' => null,
        'key' => "refund-{$order->id}-0-all",
    ]])
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->refunded_amount)->toBe(2800)
        ->and($order->fresh()->notes)->toContain('Cancelled and refunded £28.00 by Robin.')
        ->and($variant->fresh()->stock)->toBe(5);
    Mail::assertQueued(OrderRefunded::class, fn (OrderRefunded $mail) => $mail->amount === 2800);
});

test('Stripe\'s refund webhook after a cancellation in the Office changes nothing more', function () {
    $variant = ProductVariant::factory()->create(['stock' => 3]);
    $order = Order::factory()->paid()->create(['total' => 2800]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id, 'quantity' => 2]);
    app(OrderRefunds::class)->cancel($order, null);

    test()->postJson('/stripe/webhook', [
        'type' => 'charge.refunded',
        'data' => ['object' => ['payment_intent' => $order->stripe_payment_intent_id, 'amount' => 2800, 'amount_refunded' => 2800]],
    ])->assertOk();

    expect($variant->fresh()->stock)->toBe(5)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('an order that has left cannot be cancelled, only refunded', function () {
    $order = Order::factory()->paid()->create();
    $order->transitionTo(OrderStatus::Posted);

    expect(fn () => app(OrderRefunds::class)->cancel($order, null))->toThrow(InvalidArgumentException::class)
        ->and($this->gateway->refunds)->toBe([]);
});

test('part of a posted order can be refunded, then the rest', function () {
    $order = Order::factory()->paid()->create(['total' => 2800]);
    $order->transitionTo(OrderStatus::Posted);

    app(OrderRefunds::class)->refund($order, 500, null);

    expect($order->fresh()->status)->toBe(OrderStatus::Posted)
        ->and($order->fresh()->refunded_amount)->toBe(500)
        ->and($this->gateway->refunds[0]['amount'])->toBe(500);

    app(OrderRefunds::class)->refund($order->fresh(), null, null);

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->refunded_amount)->toBe(2800)
        ->and($this->gateway->refunds[1]['amount'])->toBeNull()
        ->and($this->gateway->refunds[1]['key'])->not->toBe($this->gateway->refunds[0]['key']);
});

test('no more can be refunded than was paid', function () {
    $order = Order::factory()->paid()->create(['total' => 2800]);
    $order->transitionTo(OrderStatus::Posted);

    expect(fn () => app(OrderRefunds::class)->refund($order, 2801, null))->toThrow(InvalidArgumentException::class)
        ->and($this->gateway->refunds)->toBe([]);
});

test('Mark as posted moves the paid posted orders and leaves the rest', function () {
    $toPost = Order::factory()->paid()->count(2)->create();
    $collection = Order::factory()->paid()->collection()->create();

    runOrderAction('mark-as-posted', [...$toPost, $collection], ['tracking_number' => ''])->assertOk();

    expect($toPost->map->fresh()->pluck('status')->unique()->all())->toBe([OrderStatus::Posted])
        ->and($collection->fresh()->status)->toBe(OrderStatus::Paid);
});

test('Cancel and refund in the Office refunds through Stripe', function () {
    $order = Order::factory()->paid()->create();

    runOrderAction('cancel-and-refund', [$order])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->gateway->refunds)->toHaveCount(1);
});

test('Refund in the Office takes a part amount in pounds', function () {
    $order = Order::factory()->paid()->create(['total' => 2800]);
    $order->transitionTo(OrderStatus::Posted);

    runOrderAction('refund', [$order], ['amount' => '7.50'])->assertOk();

    expect($order->fresh()->refunded_amount)->toBe(750);
});

test('the To post lens shows paid posted orders only, oldest first', function () {
    $newer = Order::factory()->paid()->create(['paid_at' => now()->subHour()]);
    $older = Order::factory()->paid()->create(['paid_at' => now()->subDay()]);
    Order::factory()->paid()->collection()->create();
    Order::factory()->create();

    $ids = $this->actingAs(officeUser())
        ->getJson('/nova-api/orders/lens/to-post')
        ->assertOk()
        ->json('resources.*.id.value');

    expect($ids)->toBe([$older->id, $newer->id]);
});

test('the For collection lens shows collection orders still to hand over', function () {
    $paid = Order::factory()->paid()->collection()->create();
    $ready = Order::factory()->paid()->collection()->create();
    $ready->transitionTo(OrderStatus::Ready);
    $collected = Order::factory()->paid()->collection()->create();
    $collected->transitionTo(OrderStatus::Ready);
    $collected->transitionTo(OrderStatus::Collected);
    Order::factory()->paid()->create();

    $ids = $this->actingAs(officeUser())
        ->getJson('/nova-api/orders/lens/for-collection')
        ->assertOk()
        ->json('resources.*.id.value');

    expect($ids)->toEqualCanonicalizing([$paid->id, $ready->id]);
});

test('the Office counts orders stuck awaiting payment', function () {
    Order::factory()->create(['created_at' => now()->subHours(2)]);
    Order::factory()->create(['created_at' => now()->subMinutes(10)]);

    $this->actingAs(officeUser())
        ->getJson('/nova-api/orders/metrics/stale-pending-orders')
        ->assertOk()
        ->assertJsonPath('value.value', 1);
});

test('packing slips download as a PDF from the signed link', function () {
    $order = Order::factory()->paid()->create([
        'name' => 'Sam Reader',
        'shipping_address' => ['name' => 'Sam Reader', 'line_one' => '2 Lansdown', 'town' => 'Stroud', 'postcode' => 'GL5 1BB'],
    ]);
    OrderItem::factory()->for($order)->create();

    $url = runOrderAction('print-packing-slips', [$order])
        ->assertOk()
        ->json('download.url');

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('packing slips cannot be had without a signed link', function () {
    $order = Order::factory()->paid()->create();

    $this->get('/exports/packing-slips?orders='.$order->id)->assertForbidden();
});
