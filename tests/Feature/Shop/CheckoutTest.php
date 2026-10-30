<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shop\Basket;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Inertia\Testing\AssertableInertia;
use Tests\Fakes\FakePaymentGateway;

/**
 * From the basket to Stripe and back: the order made on the way, what
 * Stripe is asked for, and the pages the customer comes back to.
 */
beforeEach(function () {
    config(['shop.checkout_enabled' => true]);

    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

/**
 * Put a product in the basket, as a visitor would.
 */
function basketWith(int $price = 2000, int $band = 300, int $quantity = 1, ?int $stock = null): ProductVariant
{
    $product = Product::factory()->onSale(price: $price, stock: $stock)->create();
    $product->postageBand->update(['amount' => $band, 'name' => 'Large letter']);
    $variant = $product->variants()->sole();

    test()->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => $quantity]);

    return $variant;
}

test('checking out makes a pending order and sends the customer to Stripe', function () {
    $variant = basketWith(price: 2000, band: 300, quantity: 2);

    $response = $this->post(route('checkout.store'), [], ['X-Inertia' => 'true']);

    $order = Order::query()->sole();

    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/cs_test_fake_1');

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->reference)->toBe('GOP-'.(10000 + $order->id))
        ->and($order->subtotal)->toBe(4000)
        ->and($order->shipping)->toBe(600)
        ->and($order->total)->toBe(4600)
        ->and($order->user_id)->toBeNull()
        ->and($order->stripe_checkout_session_id)->toBe('cs_test_fake_1')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->only(['product_variant_id', 'unit_price', 'quantity', 'postage_band', 'unit_postage']))->toBe([
            'product_variant_id' => $variant->id,
            'unit_price' => 2000,
            'quantity' => 2,
            'postage_band' => 'Large letter',
            'unit_postage' => 300,
        ]);
});

test('Stripe is asked for the lines at our prices, a UK address and the postage', function () {
    basketWith(price: 2000, band: 300, quantity: 2);

    $this->post(route('checkout.store'));

    $order = Order::query()->sole();
    $session = $this->gateway->lastSession();

    expect($session['mode'])->toBe('payment')
        ->and($session['line_items'][0]['quantity'])->toBe(2)
        ->and($session['line_items'][0]['price_data']['unit_amount'])->toBe(2000)
        ->and($session['line_items'][0]['price_data']['currency'])->toBe('gbp')
        ->and($session['line_items'][0]['price_data']['product_data'])->not->toHaveKey('description')
        ->and($session['shipping_address_collection'])->toBe(['allowed_countries' => ['GB']])
        ->and($session['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'])->toBe(600)
        ->and($session['shipping_options'][0]['shipping_rate_data']['display_name'])->toBe('UK postage')
        ->and($session['metadata'])->toBe(['order_id' => (string) $order->id, 'reference' => $order->reference])
        ->and($session['payment_intent_data']['metadata']['order_id'])->toBe((string) $order->id)
        ->and($session['customer_creation'])->toBe('if_required')
        ->and($session)->not->toHaveKey('customer')
        ->and($session['success_url'])->toEndWith('/checkout/success?session_id={CHECKOUT_SESSION_ID}');
});

test('a shirt goes to Stripe named with its colour and size', function () {
    $shirt = Product::factory()->create(['name' => 'Logo tee']);
    $variant = ProductVariant::factory()->for($shirt)->shirt('Black', 'M')->create();
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->post(route('checkout.store'));

    expect($this->gateway->lastSession()['line_items'][0]['price_data']['product_data'])
        ->toMatchArray(['name' => 'Logo tee', 'description' => 'Black / M']);
});

test('a collection order asks Stripe for no delivery address and charges no postage', function () {
    basketWith(price: 2000, band: 300);
    $this->put(route('basket.fulfilment'), ['fulfilment' => 'collection']);

    $this->post(route('checkout.store'));

    $session = $this->gateway->lastSession();

    expect($session)->not->toHaveKey('shipping_address_collection')
        ->and($session['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'])->toBe(0)
        ->and($session['shipping_options'][0]['shipping_rate_data']['display_name'])->toContain('Collect')
        ->and(Order::query()->sole()->total)->toBe(2000);
});

test('a signed-in customer is checked out as their Stripe customer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    basketWith();

    $this->post(route('checkout.store'));

    expect($this->gateway->customersFor)->toBe([$user->id])
        ->and($this->gateway->lastSession()['customer'])->toBe('cus_test_'.$user->id)
        ->and(Order::query()->sole()->user_id)->toBe($user->id);
});

test('an empty basket cannot be checked out', function () {
    $this->post(route('checkout.store'))->assertSessionHasErrors('basket');

    expect(Order::query()->exists())->toBeFalse()
        ->and($this->gateway->sessions)->toBe([]);
});

test('a basket holding something sold out cannot be checked out', function () {
    $variant = basketWith(stock: 2);
    $variant->update(['stock' => 0]);

    $this->post(route('checkout.store'))->assertSessionHasErrors('basket');

    expect(Order::query()->exists())->toBeFalse();
});

test('if Stripe cannot be reached the order is cancelled and the basket kept', function () {
    basketWith();
    $this->gateway->failNextCheckout = true;

    $this->post(route('checkout.store'))->assertSessionHasErrors('basket');

    expect(Order::query()->sole()->status)->toBe(OrderStatus::Cancelled)
        ->and(app(Basket::class)->isEmpty())->toBeFalse();
});

test('coming back from Stripe shows the order and empties the basket', function () {
    basketWith();
    $this->post(route('checkout.store'));
    $order = Order::query()->sole();

    $this->get(route('checkout.success', ['session_id' => $order->stripe_checkout_session_id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Thanks')
            ->where('order.reference', $order->reference)
            ->where('order.status', 'pending'));

    expect(app(Basket::class)->isEmpty())->toBeTrue();
});

test('the thank-you page shows nobody else\'s order', function () {
    $order = Order::factory()->create();

    $this->get(route('checkout.success', ['session_id' => $order->stripe_checkout_session_id]))
        ->assertRedirect(route('Shop'));
});

test('turning back from Stripe returns to the basket as it was', function () {
    basketWith();

    $this->get(route('checkout.cancelled'))->assertRedirect(route('basket.show'));

    expect(app(Basket::class)->isEmpty())->toBeFalse();
});

test('checkout is closed until the shop is open for business', function () {
    config(['shop.checkout_enabled' => false]);

    $this->post(route('checkout.store'))->assertNotFound();
});

test('a guest\'s signed link shows their order', function () {
    $order = Order::factory()->paid()->create();

    $this->get($order->guestUrl())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Order')
            ->where('order.reference', $order->reference)
            ->where('order.status_label', 'Paid'));
});

test('an order cannot be seen without a signed link', function () {
    $order = Order::factory()->paid()->create();

    $this->get("/orders/{$order->reference}")->assertForbidden();
});

test('an unpaid order has nothing to show', function () {
    $order = Order::factory()->create();

    $this->get($order->guestUrl())->assertNotFound();
});

test('a product on sale is charged at its sale price, in the basket, at Stripe and on the order', function () {
    $product = Product::factory()->create();
    $product->postageBand->update(['amount' => 300]);
    $variant = ProductVariant::factory()->for($product)->create(['price' => 5000, 'sale_price' => 2500]);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lines.0.unit_price', 2500)
            ->where('lines.0.struck_price', 5000)
            ->where('subtotal', 5000));

    $this->post(route('checkout.store'));

    $order = Order::query()->sole();

    expect($this->gateway->lastSession()['line_items'][0]['price_data']['unit_amount'])->toBe(2500)
        ->and($order->items->first()->unit_price)->toBe(2500)
        ->and($order->subtotal)->toBe(5000)
        ->and($order->total)->toBe(5600);
});
