<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use App\Support\SiteMeta;
use Illuminate\Support\Facades\Storage;

/**
 * What the shop's pages tell Facebook, X, WhatsApp and search engines.
 */
beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The meta a page in the shop is rendered with.
 *
 * @return array<string, mixed>
 */
function shopMeta(string $url): array
{
    return test()->get($url)->assertOk()->viewData('page')['props']['meta'];
}

function metaForProduct(array $attributes = [], int $price = 2500, ?int $stock = null): array
{
    $category = ShopCategory::factory()->create(['slug' => 'art-prints']);
    $product = Product::factory()->for($category)->onSale($price, $stock)->create(['slug' => 'record-deck', ...$attributes]);

    return [...shopMeta(route('shop.product', [$category, $product])), 'product' => $product];
}

test('the shop front page is described in the words set in Shop settings', function () {
    ShopSettings::current()->update(['meta_description' => "Riso prints\n  and records."]);

    $meta = shopMeta(route('Shop'));

    expect($meta['title'])->toBe('The Good on Paper shop')
        ->and($meta['description'])->toBe('Riso prints and records.')
        ->and($meta['url'])->toBe(route('Shop'))
        ->and($meta['type'])->toBe('website');
});

test('the shop front page falls back on the site\'s description when none is set', function () {
    ShopSettings::current()->update(['meta_description' => null]);

    expect(shopMeta(route('Shop'))['description'])->toBe(SiteMeta::DESCRIPTION);
});

test('a category speaks with its own name, description and picture', function () {
    $category = ShopCategory::factory()->create([
        'name' => 'Art prints',
        'description' => "Riso prints,\n  made in Stroud.",
        'image' => 'shop/prints.webp',
    ]);

    $meta = shopMeta(route('shop.category', $category));

    expect($meta['title'])->toBe('Art prints')
        ->and($meta['description'])->toBe('Riso prints, made in Stroud.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('shop/prints.webp'))
        ->and($meta['url'])->toBe(route('shop.category', $category));
});

test('a category with nothing written about it still says what it is', function () {
    $category = ShopCategory::factory()->create(['name' => 'Vinyl', 'description' => null, 'image' => null]);

    expect(shopMeta(route('shop.category', $category))['description'])->toBe('Vinyl from the Good on Paper shop.');
});

test('a product speaks with its name, summary and picture', function () {
    $meta = metaForProduct(['name' => 'Record Deck', 'summary' => 'A riso print.', 'image' => 'shop/deck.webp']);

    expect($meta['title'])->toBe('Record Deck')
        ->and($meta['description'])->toBe('A riso print.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('shop/deck.webp'))
        ->and($meta['type'])->toBe('product')
        ->and($meta['jsonLd']['@type'])->toBe('Product')
        ->and($meta['jsonLd']['image'])->toBe(Storage::disk('do')->url('shop/deck.webp'));
});

test('a product with no summary is described by the opening of its description, as plain words', function () {
    $meta = metaForProduct(['summary' => null, 'description' => '<h2>Fish &amp; chips</h2><p>A <strong>limited</strong>&nbsp;print.</p>']);

    expect($meta['description'])->toBe('Fish & chips A limited print.');
});

test('a product with nothing written about it still says what it is', function () {
    $meta = metaForProduct(['name' => 'Record Deck', 'summary' => null, 'description' => null]);

    expect($meta['description'])->toBe('Record Deck, from the Good on Paper shop.');
});

test('the tags reach the page itself, where a link preview reads them', function () {
    $category = ShopCategory::factory()->create();
    $product = Product::factory()->for($category)->onSale()->create(['name' => 'Record Deck', 'summary' => 'A riso print.']);

    $this->get(route('shop.product', [$category, $product]))
        ->assertSee('<meta property="og:title" content="Record Deck">', false)
        ->assertSee('<meta property="og:type" content="product">', false)
        ->assertSee('<meta name="description" content="A riso print.">', false);
});

test('no price is offered to search engines while checkout is closed', function () {
    config(['shop.checkout_enabled' => false]);

    expect(metaForProduct()['jsonLd'])->not->toHaveKey('offers');
});

test('with checkout open, a product offers its price', function () {
    config(['shop.checkout_enabled' => true]);

    expect(metaForProduct(price: 1250)['jsonLd']['offers'])->toMatchArray([
        '@type' => 'Offer',
        'price' => '12.50',
        'priceCurrency' => 'GBP',
        'availability' => 'https://schema.org/InStock',
    ]);
});

test('a product whose variants differ in price offers the range', function () {
    config(['shop.checkout_enabled' => true]);

    $category = ShopCategory::factory()->create();
    $product = Product::factory()->for($category)->onSale(1000)->create();
    ProductVariant::factory()->for($product)->shirt('Black', 'M')->create(['price' => 2000]);

    expect(shopMeta(route('shop.product', [$category, $product]))['jsonLd']['offers'])->toMatchArray([
        '@type' => 'AggregateOffer',
        'lowPrice' => '10.00',
        'highPrice' => '20.00',
        'offerCount' => 2,
    ]);
});

test('a sold out product says so', function () {
    config(['shop.checkout_enabled' => true]);

    expect(metaForProduct(stock: 0)['jsonLd']['offers']['availability'])->toBe('https://schema.org/OutOfStock');
});
