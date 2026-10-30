<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopSettings;
use App\Services\Shop\Basket;
use Inertia\Testing\AssertableInertia;

/**
 * The basket: filling it, changing it, choosing post or collection, and
 * the totals it shows before the customer pays.
 */
beforeEach(function () {
    config(['shop.checkout_enabled' => true]);
});

/**
 * A product on sale at the given price, posted in the given band.
 */
function basketProduct(int $price = 2500, int $band = 300, ?int $stock = null): ProductVariant
{
    $product = Product::factory()->onSale(price: $price, stock: $stock)->create();
    $product->postageBand->update(['amount' => $band]);

    return $product->variants()->sole();
}

test('a variant can be added to the basket by anyone', function () {
    $variant = basketProduct();

    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertRedirect(route('basket.show'));

    expect(app(Basket::class)->count())->toBe(2);
});

test('adding the same thing again adds to the line', function () {
    $variant = basketProduct();

    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 3]);

    expect(app(Basket::class)->lines()->sole()->quantity)->toBe(5);
});

test('a line holds no more than ten, nor more than are left', function (?int $stock, int $kept) {
    $variant = basketProduct(stock: $stock);

    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 8]);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 8]);

    expect(app(Basket::class)->lines()->sole()->quantity)->toBe($kept);
})->with([
    'no limit on stock' => [null, 10],
    'three left' => [3, 3],
]);

test('something sold out cannot be added', function () {
    $variant = basketProduct(stock: 0);

    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');

    expect(app(Basket::class)->isEmpty())->toBeTrue();
});

test('something not on sale cannot be added', function () {
    $variant = basketProduct();
    $variant->product->update(['is_published' => false]);

    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');
});

test('a line\'s quantity can be changed, and nought takes it out', function () {
    $variant = basketProduct();
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->patch(route('basket.items.update', $variant), ['quantity' => 4]);
    expect(app(Basket::class)->count())->toBe(4);

    $this->patch(route('basket.items.update', $variant), ['quantity' => 0]);
    expect(app(Basket::class)->isEmpty())->toBeTrue();
});

test('a line can be removed', function () {
    $variant = basketProduct();
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->delete(route('basket.items.destroy', $variant))->assertRedirect(route('basket.show'));

    expect(app(Basket::class)->isEmpty())->toBeTrue();
});

test('the basket shows each line, the postage and the total', function () {
    $shirt = basketProduct(price: 2000, band: 300);
    $poster = basketProduct(price: 1500, band: 600);
    $this->post(route('basket.items.store'), ['variant_id' => $shirt->id, 'quantity' => 2]);
    $this->post(route('basket.items.store'), ['variant_id' => $poster->id, 'quantity' => 1]);

    $this->get(route('basket.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Basket')
            ->has('lines', 2)
            ->where('lines.0.variant_id', $shirt->id)
            ->where('lines.0.subtotal', 4000)
            ->where('fulfilment', 'post')
            ->where('subtotal', 5500)
            ->where('postage.amount', 1000)
            ->where('postage.uncapped', 1200)
            ->where('postage.capped', true)
            ->where('total', 6500)
            ->where('collection.address', ['Lansdown', 'Stroud', 'GL5 1BB']));
});

test('choosing collection takes the postage off', function () {
    $variant = basketProduct(price: 2000, band: 300);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->put(route('basket.fulfilment'), ['fulfilment' => 'collection'])
        ->assertRedirect(route('basket.show'));

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fulfilment', 'collection')
            ->where('postage.amount', 0)
            ->where('total', 2000));
});

test('only post and collection can be chosen', function () {
    $this->put(route('basket.fulfilment'), ['fulfilment' => 'courier'])
        ->assertSessionHasErrors('fulfilment');
});

test('the postage cap is the one set in shop settings', function () {
    ShopSettings::current()->update(['postage_cap' => 500]);
    $variant = basketProduct(band: 300);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 3]);

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('postage.amount', 500));
});

test('the basket uses today\'s price, not the price when it was added', function () {
    $variant = basketProduct(price: 2000);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $variant->update(['price' => 2400]);

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('subtotal', 2400));
});

test('something taken off sale drops out of the basket', function () {
    $kept = basketProduct();
    $withdrawn = basketProduct();
    $this->post(route('basket.items.store'), ['variant_id' => $kept->id, 'quantity' => 1]);
    $this->post(route('basket.items.store'), ['variant_id' => $withdrawn->id, 'quantity' => 1]);

    $withdrawn->product->delete();

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('lines', 1)
            ->where('lines.0.variant_id', $kept->id));

    expect(app(Basket::class)->count())->toBe(1);
});

test('something that sells out while in the basket stays, marked unavailable', function () {
    $variant = basketProduct(stock: 5);
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);

    $variant->update(['stock' => 0]);

    $this->get(route('basket.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lines.0.available', false));
});

test('every page knows how many things are in the basket', function () {
    $variant = basketProduct();
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 3]);

    $this->get(route('Shop'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('basket.count', 3));
});

test('the basket is hidden until the shop is open for business', function () {
    config(['shop.checkout_enabled' => false]);
    $variant = basketProduct();

    $this->get(route('basket.show'))->assertNotFound();
    $this->post(route('basket.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])->assertNotFound();
    $this->get(route('Shop'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('basket', null));
});
