<?php

use App\Models\PostageBand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use Illuminate\Database\QueryException;

/**
 * The shop's catalogue: what the migrations put in place, and what decides
 * whether the public can see and buy a product.
 */
test('the migrations seed the four postage bands at their placeholder amounts', function () {
    expect(PostageBand::query()->ordered()->pluck('amount', 'name')->all())->toBe([
        'Letter' => 200,
        'Large letter' => 300,
        'Parcel' => 400,
        'Tube' => 600,
    ]);
});

test('the migrations seed the shop settings with a ten pound postage cap and the collection address', function () {
    $settings = ShopSettings::current();

    expect($settings->postage_cap)->toBe(1000)
        ->and($settings->collection_name)->toBe('Good on Paper, Centre for Science & Art')
        ->and($settings->collectionAddressLines())->toBe(['Lansdown', 'Stroud', 'GL5 1BB']);
});

test('a published product with a band, a published shelf and a variant is on sale', function () {
    $product = Product::factory()->onSale()->create();

    expect(Product::onSale()->pluck('id')->all())->toBe([$product->id]);
});

test('a product is kept out of the shop when anything it needs is missing', function (Closure $spoil) {
    $product = Product::factory()->onSale()->create();

    $spoil($product);

    expect(Product::onSale()->exists())->toBeFalse();
})->with([
    'unpublished' => fn (Product $product) => $product->update(['is_published' => false]),
    'no postage band' => fn (Product $product) => $product->update(['postage_band_id' => null]),
    'unpublished shelf' => fn (Product $product) => $product->shopCategory->update(['is_published' => false]),
    'no variants' => fn (Product $product) => $product->variants()->delete(),
    'deleted' => fn (Product $product) => $product->delete(),
]);

test('a variant is named by its colour and size, or Standard when it has neither', function (?string $colour, ?string $size, string $name) {
    $variant = ProductVariant::factory()->make(['colour' => $colour, 'size' => $size]);

    expect($variant->displayName())->toBe($name);
})->with([
    [null, null, 'Standard'],
    ['Black', 'M', 'Black / M'],
    ['Black', null, 'Black'],
    [null, 'XL', 'XL'],
]);

test('sizes rank smallest first, with unknown sizes last', function () {
    $sizes = collect(['XL', 'S', 'One size', 'M', 'xs', 'XXL', 'L'])
        ->sortBy(fn (string $size): int => ProductVariant::sizeRank($size))
        ->values()
        ->all();

    expect($sizes)->toBe(['xs', 'S', 'M', 'L', 'XL', 'XXL', 'One size']);
});

test('a product is sold out only when every variant has run out', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->shirt('Black', 'S')->soldOut()->create();
    $medium = ProductVariant::factory()->for($product)->shirt('Black', 'M')->create(['stock' => 1]);

    expect($product->fresh()->isSoldOut())->toBeFalse();

    $medium->update(['stock' => 0]);

    expect($product->fresh()->isSoldOut())->toBeTrue();
});

test('empty stock means there is no limit', function () {
    expect(ProductVariant::factory()->make(['stock' => null])->isInStock())->toBeTrue()
        ->and(ProductVariant::factory()->make(['stock' => 0])->isInStock())->toBeFalse();
});

test('a postage band in use cannot be deleted', function () {
    $product = Product::factory()->create();

    expect(fn () => $product->postageBand->delete())->toThrow(QueryException::class);
});

test('a colour and size appear only once on a product', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->shirt('Black', 'M')->create();

    expect(fn () => ProductVariant::factory()->for($product)->shirt('Black', 'M')->create())
        ->toThrow(QueryException::class);
});

test('a deleted shelf leaves its products without one', function () {
    $category = ShopCategory::factory()->create();
    $product = Product::factory()->for($category)->create();

    $category->delete();

    expect($product->fresh()->shop_category_id)->toBeNull();
});

test('a variant is on sale only while its sale price is below its price', function (?int $salePrice, bool $onSale, int $charged) {
    $variant = ProductVariant::factory()->make(['price' => 5000, 'sale_price' => $salePrice]);

    expect($variant->isOnSale())->toBe($onSale)
        ->and($variant->currentPrice())->toBe($charged)
        ->and($variant->struckPrice())->toBe($onSale ? 5000 : null);
})->with([
    'no sale price' => [null, false, 5000],
    'sale price below' => [2500, true, 2500],
    'sale price the same' => [5000, false, 5000],
    'sale price above' => [6000, false, 5000],
]);

