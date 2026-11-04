<?php

use App\Models\Order;
use App\Models\User;

const SIDEBAR_PURCHASES = '[data-sidebar="menu-button"][href="/dashboard/orders"]';

test('an account with purchases finds them from the sidebar and the main panel', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create();
    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('PURCHASES')
        ->assertVisible(SIDEBAR_PURCHASES)
        ->assertVisible('main a[href="/dashboard/orders"]')
        ->click('main a[href="/dashboard/orders"]')
        ->assertPathIs('/dashboard/orders')
        ->assertSee((string) $order->reference)
        ->assertDontSee('Advert payments')
        ->click((string) $order->reference)
        ->assertPathIs('/dashboard/orders/'.$order->reference)
        ->assertAttribute(SIDEBAR_PURCHASES, 'data-active', 'true')
        ->assertNoJavaScriptErrors();
});

test('an account with no purchases is offered neither', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')
        ->assertSee('Manage profile')
        ->assertDontSee('PURCHASES')
        ->assertDontSee('View purchases')
        ->assertNoJavaScriptErrors();
});
