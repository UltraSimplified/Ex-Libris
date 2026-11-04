<?php

use App\Models\PostageBand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use App\Models\User;
use App\Nova\Product as ProductResource;
use App\Services\Shop\VariantGrid;

/*
 * The sortable trait keeps its answer for the rest of the process, which in
 * tests would carry from one request to the next.
 */
beforeEach(function () {
    ProductResource::disableSortabilityCache();
});

/**
 * The shop's catalogue as the office keeps it, in the Office.
 */
function shopOffice(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

/**
 * What the Office sends to create or update a product.
 *
 * @return array<string, mixed>
 */
function productForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lansdown print',
        'slug' => 'lansdown-print',
        'shopCategory' => ShopCategory::factory()->create()->id,
        'postageBand' => PostageBand::query()->first()->id,
        'summary' => 'A riso print of Lansdown.',
        'standard_price' => '12.50',
        'standard_stock' => '5',
        'is_published' => true,
    ], $overrides);
}

test('a print is created with its price and stock on a single standard variant', function () {
    $this->actingAs(shopOffice())
        ->postJson('/nova-api/products', productForm())
        ->assertCreated();

    $product = Product::query()->sole();

    expect($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->displayName())->toBe('Standard')
        ->and($product->variants->first()->price)->toBe(1250)
        ->and($product->variants->first()->stock)->toBe(5)
        ->and(Product::onSale()->pluck('id')->all())->toBe([$product->id]);
});

test('changing a print\'s price and stock changes its standard variant', function () {
    $product = Product::factory()->onSale(price: 1250, stock: 5)->create();

    $this->actingAs(shopOffice())
        ->putJson("/nova-api/products/{$product->id}", productForm([
            'slug' => $product->slug,
            'standard_price' => '15',
            'standard_stock' => '',
        ]))
        ->assertOk();

    $variant = $product->variants()->sole();

    expect($variant->price)->toBe(1500)
        ->and($variant->stock)->toBeNull();
});

test('a product cannot be published without a postage band or a category', function (string $field) {
    $this->actingAs(shopOffice())
        ->postJson('/nova-api/products', productForm([$field => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with(['postageBand', 'shopCategory']);

test('an unpublished product may be saved without a postage band', function () {
    $this->actingAs(shopOffice())
        ->postJson('/nova-api/products', productForm(['postageBand' => '', 'is_published' => false]))
        ->assertCreated();
});

test('a shirt\'s form leaves price and stock to its colours and sizes', function () {
    $shirt = Product::factory()->create();
    ProductVariant::factory()->for($shirt)->shirt('Black', 'M')->create();

    $fields = $this->actingAs(shopOffice())
        ->getJson("/nova-api/products/{$shirt->id}/update-fields")
        ->assertOk()
        ->json('fields');

    expect(collect($fields)->pluck('attribute'))->not->toContain('standard_price')
        ->not->toContain('standard_stock');
});

test('a product cannot be given the same colour and size twice', function () {
    $product = Product::factory()->onSale()->create();

    $this->actingAs(shopOffice())
        ->postJson('/nova-api/product-variants', [
            'product' => $product->id,
            'price' => '10',
            'position' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size');
});

/**
 * The names of a category's products, in the order the Office lists them.
 *
 * @return list<string>
 */
function officeOrderOf(ShopCategory $category): array
{
    return collect(test()->getJson("/nova-api/products?viaResource=shop-categories&viaResourceId={$category->id}&viaRelationship=products&relationshipType=hasMany")
        ->assertOk()
        ->json('resources'))
        ->map(fn (array $resource): string => collect($resource['fields'])->firstWhere('attribute', 'name')['value'])
        ->all();
}

test('dragging products on their category\'s page sets the order the shop shows them in', function () {
    $category = ShopCategory::factory()->create(['is_published' => true]);
    $first = Product::factory()->for($category)->onSale()->create(['name' => 'First']);
    $second = Product::factory()->for($category)->onSale()->create(['name' => 'Second']);
    $third = Product::factory()->for($category)->onSale()->create(['name' => 'Third']);

    $this->actingAs(shopOffice());

    expect(officeOrderOf($category))->toBe(['First', 'Second', 'Third']);

    $this->postJson('/nova-vendor/nova-sortable/sort/products/update-order', [
        'resourceId' => null,
        'resourceIds' => [$third->id, $first->id, $second->id],
        'relatedResource' => null,
        'relationshipType' => 'hasMany',
        'viaRelationship' => 'products',
        'viaResource' => 'shop-categories',
        'viaResourceId' => $category->id,
    ])->assertNoContent();

    expect(officeOrderOf($category))->toBe(['Third', 'First', 'Second']);

    $this->get(route('shop.category', $category))
        ->assertInertia(fn ($page) => $page
            ->where('products.0.name', 'Third')
            ->where('products.1.name', 'First')
            ->where('products.2.name', 'Second'));
});

test('moving a product to the start only shifts the others in its category', function () {
    $category = ShopCategory::factory()->create();
    $first = Product::factory()->for($category)->create();
    $last = Product::factory()->for($category)->create();
    $elsewhere = Product::factory()->for(ShopCategory::factory())->create();

    $this->actingAs(shopOffice())
        ->postJson('/nova-vendor/nova-sortable/sort/products/move-to-start', [
            'resourceId' => $last->id,
            'relatedResource' => null,
            'relationshipType' => 'hasMany',
            'viaRelationship' => 'products',
            'viaResource' => 'shop-categories',
            'viaResourceId' => $category->id,
        ])
        ->assertNoContent();

    expect($last->fresh()->position)->toBeLessThan($first->fresh()->position)
        ->and($elsewhere->fresh()->position)->toBe(1);
});

test('products cannot be dragged on the main list, where categories are mixed', function () {
    Product::factory()->create();

    $resource = $this->actingAs(shopOffice())->getJson('/nova-api/products')->assertOk()->json('resources.0');

    expect($resource['sort_on_index'])->toBeFalse()
        ->and($resource['sort_on_has_many'])->toBeTrue();
});

test('each category numbers its own products, and a new one goes to the end', function () {
    $prints = ShopCategory::factory()->create();
    $shirts = ShopCategory::factory()->create();

    Product::factory()->for($prints)->create();

    expect(Product::factory()->for($prints)->create()->position)->toBe(2)
        ->and(Product::factory()->for($shirts)->create()->position)->toBe(1);
});

test('a product moved to another category goes to the end of it', function () {
    $prints = ShopCategory::factory()->create();
    $shirts = ShopCategory::factory()->create();
    Product::factory()->for($shirts)->count(2)->create();
    $product = Product::factory()->for($prints)->create();

    $product->update(['shop_category_id' => $shirts->id]);

    expect($product->fresh()->position)->toBe(3);
});

test('postage bands and shop settings can be edited but never made or removed', function (string $uriKey, Closure $model) {
    $office = shopOffice();
    $record = $model();

    $this->actingAs($office)->postJson("/nova-api/{$uriKey}", [])->assertForbidden();
    $this->actingAs($office)->deleteJson("/nova-api/{$uriKey}?resources[]={$record->id}");

    expect($record->fresh())->not->toBeNull();
})->with([
    'postage bands' => ['postage-bands', fn () => PostageBand::query()->first()],
    'shop settings' => ['shop-settings', fn () => ShopSettings::current()],
]);

/**
 * What the Office sends to save the shop's settings.
 *
 * @return array<string, mixed>
 */
function shopSettingsForm(ShopSettings $settings, ?string $description): array
{
    return [
        'postage_cap' => $settings->postage_cap,
        'collection_name' => $settings->collection_name,
        'collection_address' => $settings->collection_address,
        'collection_hours' => $settings->collection_hours,
        'meta_description' => $description,
    ];
}

test('the office can change or clear the shop page\'s description', function (?string $description) {
    $settings = ShopSettings::current();

    $this->actingAs(shopOffice())
        ->putJson("/nova-api/shop-settings/{$settings->id}", shopSettingsForm($settings, $description))
        ->assertOk();

    expect($settings->fresh()->meta_description)->toBe($description);
})->with(['changed' => 'Riso prints and records.', 'cleared' => null]);

test('the shop page\'s description has to be short enough to be of use', function () {
    $settings = ShopSettings::current();

    $this->actingAs(shopOffice())
        ->putJson("/nova-api/shop-settings/{$settings->id}", shopSettingsForm($settings, str_repeat('a', 301)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('meta_description');
});

test('the shop page starts out with a description already in the box', function () {
    expect(ShopSettings::current()->meta_description)->toContain('Good on Paper');
});

test('only the office can reach the shop in the Office', function () {
    $this->actingAs(User::factory()->create(['email' => 'reader@example.com']))
        ->getJson('/nova-api/products')
        ->assertForbidden();
});

test('colours are read with their optional swatches', function () {
    expect(VariantGrid::parseColours('Black #111111, white #FFFFFF ,Natural,, Black'))->toBe([
        ['name' => 'Black', 'hex' => '#111111'],
        ['name' => 'white', 'hex' => '#ffffff'],
        ['name' => 'Natural', 'hex' => null],
    ]);
});

test('sizes are put smallest first and written the standard way', function () {
    expect(VariantGrid::parseSizes('xl, S, One size, m, S'))->toBe(['S', 'M', 'XL', 'One size']);
});

test('generating colours and sizes makes every combination once', function () {
    $shirt = Product::factory()->create();
    ProductVariant::factory()->for($shirt)->shirt('Black', 'M')->create(['price' => 1800, 'stock' => 2]);

    $result = app(VariantGrid::class)->generate(
        $shirt,
        VariantGrid::parseColours('Black #111111, White #ffffff'),
        VariantGrid::parseSizes('S, M, L'),
        2000,
        10,
    );

    expect($result)->toBe(['made' => 5, 'kept' => 1, 'removedStandard' => false])
        ->and($shirt->variants()->count())->toBe(6)
        ->and($shirt->variants->map->displayName()->all())->toBe([
            'Black / S', 'Black / M', 'Black / L', 'White / S', 'White / M', 'White / L',
        ])
        ->and($shirt->variants()->where('colour', 'Black')->where('size', 'M')->value('price'))->toBe(1800);
});

test('a deleted colour and size is brought back rather than made again', function () {
    $shirt = Product::factory()->create();
    $gone = ProductVariant::factory()->for($shirt)->shirt('Black', 'M')->create();
    $gone->delete();

    $result = app(VariantGrid::class)->generate($shirt, [['name' => 'Black', 'hex' => null]], ['M'], 2000, null);

    expect($result['made'])->toBe(1)
        ->and($gone->fresh()->trashed())->toBeFalse()
        ->and($gone->fresh()->price)->toBe(2000);
});

test('giving a print colours or sizes takes away its standard variant', function () {
    $product = Product::factory()->onSale()->create();

    $result = app(VariantGrid::class)->generate($product, [], ['A3', 'A2'], 3000, null);

    expect($result['removedStandard'])->toBeTrue()
        ->and($product->variants()->get()->map->displayName()->all())->toBe(['A3', 'A2']);
});

test('a print can be put on sale with a sale price, and taken off again', function () {
    $office = shopOffice();

    $this->actingAs($office)
        ->postJson('/nova-api/products', productForm(['standard_price' => '50', 'standard_sale_price' => '25']))
        ->assertCreated();

    $product = Product::query()->sole();

    expect($product->variants()->sole()->only(['price', 'sale_price']))->toBe(['price' => 5000, 'sale_price' => 2500]);

    $this->actingAs($office)
        ->putJson("/nova-api/products/{$product->id}", productForm(['slug' => $product->slug, 'standard_price' => '50', 'standard_sale_price' => '']))
        ->assertOk();

    expect($product->variants()->sole()->sale_price)->toBeNull();
});

test('generating colours and sizes can put them all on sale', function () {
    $shirt = Product::factory()->create();

    app(VariantGrid::class)->generate($shirt, [['name' => 'Black', 'hex' => null]], ['S', 'M'], 3000, null, 2000);

    expect($shirt->variants()->pluck('sale_price')->unique()->all())->toBe([2000]);
});
