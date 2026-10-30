<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use Inertia\Testing\AssertableInertia;

/**
 * The shop as the public browses it: shelves, what is on each, and each
 * product's colours and sizes.
 */
test('the shop lists the shelves with something on sale, in the office\'s order', function () {
    $posters = ShopCategory::factory()->create(['name' => 'Posters', 'slug' => 'posters', 'position' => 2]);
    $prints = ShopCategory::factory()->create(['name' => 'Art prints', 'slug' => 'art-prints', 'position' => 1]);
    $empty = ShopCategory::factory()->create(['name' => 'Vinyl', 'slug' => 'vinyl']);
    $hidden = ShopCategory::factory()->unpublished()->create(['name' => 'T-shirts']);

    Product::factory()->for($posters)->onSale()->create();
    Product::factory()->for($prints)->onSale()->count(2)->create();
    Product::factory()->for($empty)->unpublished()->onSale()->create();
    Product::factory()->for($hidden)->onSale()->create();

    $this->get(route('Shop'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Shop')
            ->has('categories', 2)
            ->where('categories.0.slug', 'art-prints')
            ->where('categories.0.products_count', 2)
            ->where('categories.1.slug', 'posters'));
});

test('a shelf shows what is on sale on it, with prices and sold-out marks', function () {
    $shirts = ShopCategory::factory()->create(['slug' => 't-shirts']);
    $shirt = Product::factory()->for($shirts)->create(['name' => 'Logo tee', 'slug' => 'logo-tee']);
    ProductVariant::factory()->for($shirt)->shirt('Black', 'M')->create(['price' => 2000]);
    ProductVariant::factory()->for($shirt)->shirt('Black', 'XXL')->create(['price' => 2200]);
    Product::factory()->for($shirts)->onSale(stock: 0)->create(['slug' => 'gone-tee', 'position' => 1]);
    Product::factory()->for($shirts)->unpublished()->onSale()->create(['slug' => 'secret-tee']);

    $this->get(route('shop.category', $shirts))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Category')
            ->has('products', 2)
            ->where('products.0.slug', 'logo-tee')
            ->where('products.0.price_from', 2000)
            ->where('products.0.price_to', 2200)
            ->where('products.0.sold_out', false)
            ->where('products.1.slug', 'gone-tee')
            ->where('products.1.sold_out', true));
});

test('an unpublished shelf is not found', function () {
    $hidden = ShopCategory::factory()->unpublished()->create();

    $this->get(route('shop.category', $hidden))->assertNotFound();
});

test('a product page offers its colours and sizes, pictures and postage', function () {
    $shirts = ShopCategory::factory()->create(['slug' => 't-shirts']);
    $shirt = Product::factory()->for($shirts)->create(['slug' => 'logo-tee']);
    ProductVariant::factory()->for($shirt)->shirt('Black', 'M', '#111111')->create();
    ProductVariant::factory()->for($shirt)->shirt('Black', 'L', '#111111')->soldOut()->create();
    ProductImage::factory()->for($shirt)->create(['colour' => 'Black']);

    $this->get(route('shop.product', ['shopCategory' => $shirts, 'product' => $shirt]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/Product')
            ->where('product.slug', 'logo-tee')
            ->has('product.variants', 2)
            ->where('product.variants.0.name', 'Black / M')
            ->where('product.variants.0.in_stock', true)
            ->where('product.variants.1.in_stock', false)
            ->where('product.images.0.colour', 'Black')
            ->where('delivery.band', $shirt->postageBand->name)
            ->where('delivery.cap', 1000)
            ->where('delivery.collection', 'Good on Paper, Centre for Science & Art'));
});

test('a product that is not on sale is not found', function (Closure $spoil) {
    $product = Product::factory()->onSale()->create();

    $spoil($product);

    $this->get(route('shop.product', ['shopCategory' => $product->shopCategory, 'product' => $product]))
        ->assertNotFound();
})->with([
    'unpublished' => fn (Product $product) => $product->update(['is_published' => false]),
    'no postage band' => fn (Product $product) => $product->update(['postage_band_id' => null]),
    'unpublished shelf' => fn (Product $product) => $product->shopCategory->update(['is_published' => false]),
]);

test('a product is found only on its own shelf', function () {
    $product = Product::factory()->onSale()->create();
    $otherShelf = ShopCategory::factory()->create();

    $this->get("/shop/{$otherShelf->slug}/{$product->slug}")->assertNotFound();
});

test('every page knows the shelves for the sub-nav under Shop', function () {
    $posters = ShopCategory::factory()->create(['name' => 'Posters', 'slug' => 'posters', 'position' => 2]);
    $prints = ShopCategory::factory()->create(['name' => 'Art prints', 'slug' => 'art-prints', 'position' => 1]);
    $empty = ShopCategory::factory()->create(['name' => 'Vinyl', 'slug' => 'vinyl']);
    $hidden = ShopCategory::factory()->unpublished()->create(['name' => 'T-shirts']);
    Product::factory()->for($posters)->onSale()->create();
    Product::factory()->for($prints)->onSale()->create();
    Product::factory()->for($empty)->unpublished()->onSale()->create();
    Product::factory()->for($hidden)->onSale()->create();

    $this->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('shopCategories', [
            ['name' => 'Art prints', 'slug' => 'art-prints'],
            ['name' => 'Posters', 'slug' => 'posters'],
        ]));
});

test('a product on sale shows its sale price with its usual price struck through', function () {
    $prints = ShopCategory::factory()->create(['slug' => 'prints']);
    $onSale = Product::factory()->for($prints)->create(['slug' => 'record-deck', 'position' => 1]);
    ProductVariant::factory()->for($onSale)->create(['price' => 5000, 'sale_price' => 2500]);
    $ignored = Product::factory()->for($prints)->create(['slug' => 'lansdown', 'position' => 2]);
    ProductVariant::factory()->for($ignored)->create(['price' => 2500, 'sale_price' => 3000]);

    $this->get(route('shop.category', $prints))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.price_from', 2500)
            ->where('products.0.struck_price', 5000)
            ->where('products.1.price_from', 2500)
            ->where('products.1.struck_price', null));

    $this->get(route('shop.product', ['shopCategory' => $prints, 'product' => $onSale]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.variants.0.price', 2500)
            ->where('product.variants.0.struck_price', 5000));
});
