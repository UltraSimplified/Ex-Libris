<?php

use App\Models\Order;
use App\Models\User;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;

/**
 * The count beside the Orders link in the Office, which tells whoever
 * opens it how much is waiting to be posted or handed over.
 */

/**
 * What the badge on the Orders link says, or null when it carries none.
 */
function ordersBadge(): ?string
{
    $request = NovaRequest::create('/nova-api/menus/main');
    $request->setUserResolver(fn (): User => User::factory()->create());

    $orders = collect(Nova::resolveMainMenu($request))
        ->filter(fn ($section): bool => $section instanceof MenuSection && (string) $section->name === 'Shop')
        ->flatMap(fn (MenuSection $section) => $section->items->all())
        ->first(fn (MenuItem $item): bool => (string) $item->name === 'Orders');

    expect($orders)->not->toBeNull();

    return $orders->resolveBadge()?->value;
}

/**
 * An order in a given state, with nothing else to it.
 */
function orderIn(Fulfilment $fulfilment, OrderStatus $status): Order
{
    return Order::factory()->paid()->create(['fulfilment' => $fulfilment, 'status' => $status]);
}

test('the Orders link counts what is still to post or hand over', function () {
    orderIn(Fulfilment::Post, OrderStatus::Paid);
    orderIn(Fulfilment::Collection, OrderStatus::Paid);
    orderIn(Fulfilment::Collection, OrderStatus::Ready);

    expect(ordersBadge())->toBe('3');
});

test('orders that have left, or never got going, are not counted', function () {
    orderIn(Fulfilment::Post, OrderStatus::Posted);
    orderIn(Fulfilment::Post, OrderStatus::Delivered);
    orderIn(Fulfilment::Collection, OrderStatus::Collected);
    orderIn(Fulfilment::Post, OrderStatus::Cancelled);
    orderIn(Fulfilment::Post, OrderStatus::Refunded);
    Order::factory()->create(['fulfilment' => Fulfilment::Post, 'status' => OrderStatus::Pending]);

    expect(ordersBadge())->toBeNull();
});

test('a posted order waiting on the shelf is the collection case, not the post one', function () {
    // Ready only exists for collection; a posted order never sits in it.
    orderIn(Fulfilment::Collection, OrderStatus::Ready);

    expect(Order::query()->awaitingOffice()->count())->toBe(1)
        ->and(OrderStatus::awaiting(Fulfilment::Post))->toBe([OrderStatus::Paid])
        ->and(OrderStatus::awaiting(Fulfilment::Collection))->toBe([OrderStatus::Paid, OrderStatus::Ready]);
});

test('nothing outstanding leaves the link with no badge at all', function () {
    expect(ordersBadge())->toBeNull();
});
