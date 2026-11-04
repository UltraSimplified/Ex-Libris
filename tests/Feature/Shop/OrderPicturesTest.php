<?php

use App\Mail\Shop\OrderConfirmed;
use App\Mail\Shop\OrderReadyForCollection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopSettings;
use App\Models\User;
use App\Services\Geocoding\PostcodeGeocoder;
use App\Services\Images\ProductThumbnail;
use App\Services\Images\StaticMap;
use App\Services\Shop\DeliveryLocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/**
 * The pictures an order is shown with: a thumbnail of each product on the
 * lines, and a map of where the order is going or being collected from.
 */
beforeEach(function () {
    Storage::fake('do');
    Http::preventStrayRequests();
});

/**
 * Put a readable picture in the bucket at the path a product would use.
 */
function storedPicture(string $path = 'shop/tee.webp'): string
{
    Storage::disk('do')->put($path, (string) Image::createImage(40, 60)
        ->encodeUsingFormat(Format::WEBP));

    return $path;
}

/**
 * A square tile, as the tile server would answer with.
 */
function fakeTiles(): void
{
    $tile = (string) Image::createImage(256, 256)->encodeUsingFormat(Format::PNG);

    Http::fake(['tile.openstreetmap.org/*' => Http::response($tile, 200, ['Content-Type' => 'image/png'])]);
}

/**
 * Put every postcode at the same place, in place of the geocoder the test
 * case stubs out to keep the suite off postcodes.io.
 */
function postcodesResolveTo(float $latitude = 51.7423, float $longitude = -2.2191): void
{
    app()->instance(PostcodeGeocoder::class, new class($latitude, $longitude) extends PostcodeGeocoder
    {
        public function __construct(private float $latitude, private float $longitude) {}

        public function locate(?string $postcode): ?array
        {
            return blank($postcode)
                ? null
                : ['latitude' => $this->latitude, 'longitude' => $this->longitude];
        }
    });
}

test('an order line keeps the picture the product had when it was bought', function () {
    $item = OrderItem::factory()->create(['image' => 'shop/as-bought.webp']);
    $item->variant->product->update(['image' => 'shop/replaced-since.webp']);

    expect($item->picture())->toBe('shop/as-bought.webp');
});

test('a line ordered before pictures were kept falls back to the product\'s own', function () {
    $item = OrderItem::factory()->create(['image' => null]);
    $item->variant->product->update(['image' => 'shop/still-there.webp']);

    expect($item->fresh()->picture())->toBe('shop/still-there.webp');
});

test('a line whose product has gone has no picture', function () {
    $item = OrderItem::factory()->create(['image' => null, 'product_variant_id' => null]);

    expect($item->picture())->toBeNull();
});

test('the order page gives each line its picture', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create();
    OrderItem::factory()->for($order)->create(['product_name' => 'Logo tee', 'image' => 'shop/tee.webp']);

    $this->actingAs($user)
        ->get(route('dashboard.orders.show', $order->reference))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.items.0.product', 'Logo tee')
            ->where('order.items.0.image', 'shop/tee.webp'));
});

test('a product picture is copied to a JPEG once, for the mail clients that will not show webp', function () {
    storedPicture();

    $first = app(ProductThumbnail::class)->url('shop/tee.webp');
    $second = app(ProductThumbnail::class)->url('shop/tee.webp');

    expect($first)->toContain('order-thumbnails/')
        ->and($first)->toEndWith('.jpg')
        ->and($second)->toBe($first);

    $stored = Storage::disk('do')->allFiles('order-thumbnails');

    expect($stored)->toHaveCount(1)
        ->and(Image::decodeBinary(Storage::disk('do')->get($stored[0]))->width())->toBe(160);
});

test('a picture that has gone from the bucket makes no thumbnail', function () {
    expect(app(ProductThumbnail::class)->url('shop/never-uploaded.webp'))->toBeNull()
        ->and(app(ProductThumbnail::class)->url(null))->toBeNull();
});

test('the confirmation email shows a thumbnail beside each line', function () {
    storedPicture();
    $order = Order::factory()->paid()->create(['email' => 'reader@example.com']);
    OrderItem::factory()->for($order)->create([
        'product_name' => 'Logo tee',
        'variant_name' => 'Black / M',
        'image' => 'shop/tee.webp',
        'quantity' => 2,
        'unit_price' => 1250,
    ]);

    (new OrderConfirmed($order))
        ->assertSeeInHtml('order-thumbnails/', false)
        ->assertSeeInHtml('Logo tee (Black / M)')
        ->assertSeeInHtml('£25.00');
});

test('a map of a place is drawn once from the tiles and kept', function () {
    fakeTiles();

    $map = app(StaticMap::class);
    $first = $map->url(51.7423, -2.2191);
    $fetched = count(Http::recorded());
    $second = $map->url(51.7423, -2.2191);

    expect($first)->toContain('maps/')
        ->and($first)->toEndWith('.png')
        ->and($second)->toBe($first);

    // The second ask is answered by the file the first one left behind.
    expect(count(Http::recorded()))->toBe($fetched)
        ->and($fetched)->toBeGreaterThan(1);

    $stored = Storage::disk('do')->allFiles('maps');
    $drawn = Image::decodeBinary(Storage::disk('do')->get($stored[0]));

    expect($stored)->toHaveCount(1)
        ->and([$drawn->width(), $drawn->height()])->toBe([$map->width(), $map->height()]);
});

test('a tile server that will not answer leaves the map undrawn', function () {
    Http::fake(['tile.openstreetmap.org/*' => Http::response('', 500)]);

    expect(app(StaticMap::class)->url(51.7423, -2.2191))->toBeNull()
        ->and(Storage::disk('do')->allFiles('maps'))->toBe([]);
});

test('a place nobody knows is not drawn at all', function () {
    expect(app(StaticMap::class)->url(null, null))->toBeNull();
});

test('a posted order is placed from its delivery postcode, once', function () {
    postcodesResolveTo(51.5, -0.1);
    $order = Order::factory()->paid()->create([
        'shipping_address' => ['line_one' => '2 Lansdown', 'town' => 'Stroud', 'postcode' => 'GL5 1BB'],
    ]);

    $place = app(DeliveryLocation::class)->for($order);

    expect($place)->toBe(['latitude' => 51.5, 'longitude' => -0.1])
        ->and($order->fresh()->delivery_latitude)->toBe(51.5);

    postcodesResolveTo(0.0, 0.0);

    expect(app(DeliveryLocation::class)->for($order->fresh()))
        ->toBe(['latitude' => 51.5, 'longitude' => -0.1]);
});

test('an order being collected is not placed by its delivery address', function () {
    postcodesResolveTo();
    $order = Order::factory()->paid()->collection()->create();

    expect(app(DeliveryLocation::class)->for($order))->toBeNull()
        ->and($order->fresh()->delivery_latitude)->toBeNull();
});

test('an order with no postcode to go on is not placed', function () {
    postcodesResolveTo();
    $order = Order::factory()->paid()->create(['shipping_address' => ['line_one' => '2 Lansdown']]);

    expect(app(DeliveryLocation::class)->for($order))->toBeNull();
});

test('the collection address is placed from the postcode in it when it is saved', function () {
    postcodesResolveTo(51.7423, -2.2191);
    $settings = ShopSettings::current();

    $settings->update(['collection_address' => "Centre for Science & Art\nLansdown\nStroud\nGL5 1BB"]);

    expect($settings->collectionPostcode())->toBe('GL5 1BB')
        ->and($settings->latitude)->toBe(51.7423)
        ->and($settings->longitude)->toBe(-2.2191);
});

test('a pin moved by hand in the Office stays where it was put', function () {
    postcodesResolveTo(51.7423, -2.2191);
    $settings = ShopSettings::current();

    $settings->update(['latitude' => 51.75, 'longitude' => -2.22]);

    expect($settings->latitude)->toBe(51.75)
        ->and($settings->longitude)->toBe(-2.22);
});

test('the page of an order to collect gives it the office to put a pin on', function () {
    postcodesResolveTo(51.7423, -2.2191);
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->collection()->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs($user)
        ->get(route('dashboard.orders.show', $order->reference))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('collection.latitude', 51.7423)
            ->where('collection.name', 'Good on Paper, Centre for Science & Art')
            ->where('deliveryMap', null));
});

test('the page of an order to post gives it a picture of where it is going', function () {
    postcodesResolveTo(51.7423, -2.2191);
    fakeTiles();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create([
        'shipping_address' => ['line_one' => '2 Lansdown', 'postcode' => 'GL5 1BB'],
    ]);
    OrderItem::factory()->for($order)->create();

    $this->actingAs($user)
        ->get(route('dashboard.orders.show', $order->reference))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('collection', null)
            ->where('deliveryMap', fn (?string $map): bool => str_contains((string) $map, 'maps/')));
});

test('the ready-to-collect email shows a map of where to come', function () {
    postcodesResolveTo(51.7423, -2.2191);
    fakeTiles();
    $order = Order::factory()->paid()->collection()->create();

    (new OrderReadyForCollection($order))
        ->assertSeeInHtml('maps/', false)
        ->assertSeeInHtml(StaticMap::CREDIT);
});
