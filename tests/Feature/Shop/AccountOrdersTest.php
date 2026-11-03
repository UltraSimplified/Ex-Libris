<?php

use App\Mail\Shop\OrderConfirmed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * An account's own orders, and a guest's order joining an account by the
 * signed link in its confirmation email.
 */
test('an account lists the orders it has paid for, newest first', function () {
    $user = User::factory()->create();
    $older = Order::factory()->for($user)->paid()->create(['paid_at' => now()->subWeek()]);
    $newer = Order::factory()->for($user)->paid()->create(['paid_at' => now()->subDay()]);
    OrderItem::factory()->for($newer)->create(['quantity' => 3]);
    Order::factory()->for($user)->create();
    Order::factory()->for(User::factory())->paid()->create();

    $this->actingAs($user)
        ->get(route('dashboard.orders.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard/orders/Index')
            ->has('orders.data', 2)
            ->where('orders.data.0.reference', $newer->reference)
            ->where('orders.data.0.item_count', 3)
            ->where('orders.data.1.reference', $older->reference));
});

test('an account sees the detail of its own order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create([
        'receipt_url' => 'https://pay.stripe.test/receipts/1',
        'billing_address' => ['line_one' => '1 High Street', 'town' => 'Stroud'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard/orders/Show')
            ->where('order.reference', $order->reference)
            ->where('order.receipt_url', 'https://pay.stripe.test/receipts/1')
            ->where('order.billing_address', ['1 High Street', 'Stroud']));
});

test('nobody sees another account\'s order, or one never paid for', function (Closure $order) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.orders.show', $order($user)))
        ->assertNotFound();
})->with([
    'someone else\'s' => fn (User $user) => Order::factory()->for(User::factory())->paid()->create(),
    'a guest\'s' => fn (User $user) => Order::factory()->paid()->create(),
    'never paid for' => fn (User $user) => Order::factory()->for($user)->create(),
]);

test('a guest order joins the account that opens its claim link', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create();

    $this->actingAs($user)
        ->get($order->claimUrl())
        ->assertRedirect(route('dashboard.orders.show', $order));

    expect($order->fresh()->user_id)->toBe($user->id);
});

test('somebody not signed in signs in first and comes back to claim', function () {
    $order = Order::factory()->paid()->create();

    $this->get($order->claimUrl())->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe($order->claimUrl());
});

test('an order cannot be claimed without the signed link', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs(User::factory()->create())
        ->get("/orders/{$order->reference}/claim")
        ->assertForbidden();

    expect($order->fresh()->user_id)->toBeNull();
});

test('an order already on one account cannot be claimed by another', function () {
    $owner = User::factory()->create();
    $order = Order::factory()->for($owner)->paid()->create();

    $this->actingAs(User::factory()->create())
        ->get($order->claimUrl())
        ->assertForbidden();

    expect($order->fresh()->user_id)->toBe($owner->id);
});

test('a guest\'s confirmation offers the claim link and an account\'s does not', function () {
    $guestOrder = Order::factory()->paid()->create();
    $accountOrder = Order::factory()->for(User::factory())->paid()->create();

    (new OrderConfirmed($guestOrder))->assertSeeInHtml('Add this order to your account');
    (new OrderConfirmed($accountOrder))->assertDontSeeInHtml('Add this order to your account');
});

test('a reader with orders is offered them on the dashboard', function () {
    $reader = User::factory()->create();
    Order::factory()->for($reader)->paid()->create();

    $this->actingAs($reader)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard')->where('auth.hasOrders', true));
});
