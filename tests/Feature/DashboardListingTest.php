<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Services\Listings\ListingLineUp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

function listingPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'The Lovely Eggs',
        'body' => 'A very loud evening in Nottingham.',
        'category_id' => Category::factory()->create()->id,
        'venue_id' => Venue::factory()->create()->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-02',
        'additional_date_information' => 'One night only',
        'event_time_description' => 'Doors 7pm',
        'ticket_price' => '£12',
        'event_link' => 'https://example.com/tickets',
        'button_text' => 'Buy tickets',
        'meta_description' => 'A very loud evening.',
    ], $overrides);
}

test('guests cannot reach the listings dashboard', function () {
    $this->get(route('dashboard.listings.index'))
        ->assertRedirect(route('login'));
});

test('the dashboard only shows the listings the user owns', function () {
    $user = User::factory()->agreed()->create();
    $mine = Listing::factory()->for($user)->create();
    $theirs = Listing::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Index')
            ->has('listings', 1)
            ->where('listings.0.id', $mine->id)
        );

    expect($theirs->user_id)->toBeNull();
});

test('a user can create a listing', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload())
        ->assertRedirect(route('dashboard.listings.index'));

    $listing = Listing::sole();

    expect($listing->user_id)->toBe($user->id)
        ->and($listing->title)->toBe('The Lovely Eggs')
        ->and($listing->slug)->toBe('the-lovely-eggs')
        ->and($listing->start_date->toDateString())->toBe('2026-09-01');
});

test('creating a listing requires the mandatory fields', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'title' => '',
            'category_id' => '',
            'start_date' => '',
        ]))
        ->assertSessionHasErrors(['title', 'category_id', 'start_date']);

    expect(Listing::count())->toBe(0);
});

test('a user can edit their own listing', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Edit')
            ->where('listing.id', $listing->id)
            ->has('categories')
            ->has('venues')
        );

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload([
            'title' => 'An updated title',
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    expect($listing->fresh()->title)->toBe('An updated title');
});

test('a user cannot edit or delete somebody else listing', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for(User::factory())->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload())
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('dashboard.listings.destroy', $listing))
        ->assertForbidden();

    expect(Listing::whereKey($listing->id)->exists())->toBeTrue();
});

test('a user can delete their own listing', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('dashboard.listings.destroy', $listing))
        ->assertRedirect(route('dashboard.listings.index'));

    expect(Listing::whereKey($listing->id)->exists())->toBeFalse();
});

/**
 * A wide test poster: a solid $edge border around a solid $centre panel.
 *
 * The two colours make cropping visible — if the sides were cut away the
 * edge colour would not survive into the output.
 */
function posterFile(int $width, int $height, string $edge, string $centre): UploadedFile
{
    $poster = Image::createImage($width, $height)->fill($edge);

    $poster->insert(
        Image::createImage((int) ($width * 0.8), (int) ($height * 0.5))->fill($centre),
        alignment: 'center',
    );

    $path = tempnam(sys_get_temp_dir(), 'poster').'.png';
    file_put_contents($path, (string) $poster->encodeUsingFormat(Format::PNG));

    return new UploadedFile($path, 'poster.png', 'image/png', null, true);
}

/**
 * Assert the pixel at x,y is the given colour, allowing for the small channel
 * shifts that lossy webp encoding introduces.
 */
function expectPixel(ImageInterface $image, int $x, int $y, string $hex): void
{
    $actual = $image->colorAt($x, $y);
    $expected = sscanf($hex, '%2x%2x%2x');
    $channels = [$actual->red()->value(), $actual->green()->value(), $actual->blue()->value()];

    foreach ($channels as $index => $channel) {
        expect(abs($channel - $expected[$index]))
            ->toBeLessThanOrEqual(4, "pixel at {$x},{$y} should be close to #{$hex}, got #{$actual->toHex()}");
    }
}

test('a thumbnail is resized, converted to webp and stored on the do disk', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 1200, 1200),
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    $thumbnail = Listing::sole()->thumbnail;

    expect($thumbnail)->toEndWith('.webp');
    Storage::disk('do')->assertExists($thumbnail);

    $stored = Image::decodeBinary(Storage::disk('do')->get($thumbnail));

    expect($stored->width())->toBe(548)
        ->and($stored->height())->toBe(462);
});

test('a wide graphic is padded rather than cropped', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'thumbnail' => posterFile(1000, 200, 'ff0000', '00ff00'),
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    $stored = Image::decodeBinary(Storage::disk('do')->get(Listing::sole()->thumbnail));

    expect($stored->width())->toBe(548)
        ->and($stored->height())->toBe(462);

    // The source is 5:1, so it lands as a band across the middle. The top of
    // the canvas is padding, and it takes the sampled edge colour.
    expectPixel($stored, 274, 4, 'ff0000');

    // The far left of the source survives, so nothing was cropped away
    // horizontally — a cover-style crop would show the centre colour here.
    expectPixel($stored, 2, 231, 'ff0000');
    expectPixel($stored, 274, 231, '00ff00');
});

test('a tall graphic is padded rather than cropped', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'thumbnail' => posterFile(200, 1000, '0000ff', 'ffff00'),
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    $stored = Image::decodeBinary(Storage::disk('do')->get(Listing::sole()->thumbnail));

    expect($stored->width())->toBe(548)
        ->and($stored->height())->toBe(462);

    // Padding down the left and right, sampled from the blue border.
    expectPixel($stored, 4, 231, '0000ff');
    expectPixel($stored, 543, 231, '0000ff');

    // The top of the source survives the fit.
    expectPixel($stored, 274, 2, '0000ff');
    expectPixel($stored, 274, 231, 'ffff00');
});

test('the pad colour is sampled from the artwork rather than defaulting to white', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'thumbnail' => posterFile(1000, 300, '1a1a1a', 'ffffff'),
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    $stored = Image::decodeBinary(Storage::disk('do')->get(Listing::sole()->thumbnail));

    expectPixel($stored, 274, 4, '1a1a1a');
});

test('a non-image upload is rejected', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'thumbnail' => UploadedFile::fake()->create('tickets.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('thumbnail');

    expect(Listing::count())->toBe(0);
});

test('replacing a thumbnail removes the old file', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('first.jpg', 900, 900),
    ]));

    $first = $listing->fresh()->thumbnail;

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('second.jpg', 900, 900),
    ]));

    $second = $listing->fresh()->thumbnail;

    expect($second)->not->toBe($first);
    Storage::disk('do')->assertMissing($first);
    Storage::disk('do')->assertExists($second);
});

test('a thumbnail can be removed without uploading a replacement', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('poster.jpg', 900, 900),
    ]));

    $stored = $listing->fresh()->thumbnail;

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'remove_thumbnail' => true,
    ]));

    expect($listing->fresh()->thumbnail)->toBeNull();
    Storage::disk('do')->assertMissing($stored);
});

test('an update without a thumbnail leaves the existing image alone', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('poster.jpg', 900, 900),
    ]));

    $stored = $listing->fresh()->thumbnail;

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'title' => 'Just a title change',
    ]));

    expect($listing->fresh()->thumbnail)->toBe($stored);
    Storage::disk('do')->assertExists($stored);
});

test('deleting a listing also deletes its thumbnail', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('poster.jpg', 900, 900),
    ]));

    $stored = $listing->fresh()->thumbnail;

    $this->actingAs($user)
        ->delete(route('dashboard.listings.destroy', $listing))
        ->assertRedirect(route('dashboard.listings.index'));

    expect(Listing::whereKey($listing->id)->exists())->toBeFalse();
    Storage::disk('do')->assertMissing($stored);
});

/**
 * The artists on the listing's bill, as stored.
 *
 * @return array<int, array{name: string, playlist: ?string, video: ?string}>
 */
function storedLineUp(Listing $listing): array
{
    return app(ListingLineUp::class)->artists($listing->fresh()->line_up);
}

test('a pasted bandcamp embed is stored widened and stripped', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => [[
            'name' => 'Los Fastidios',
            'playlist' => '<iframe style="border: 0; width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=3562433514/size=large/" seamless><a href="https://losfastidiosofficial.bandcamp.com/album/lovesteady">Lovesteady</a></iframe>',
        ]],
    ]))->assertRedirect(route('dashboard.listings.index'));

    $stored = storedLineUp(Listing::sole())[0]['playlist'];

    expect($stored)->toContain('width: 100%')
        ->and($stored)->toContain('height: 470px')
        ->and($stored)->not->toContain('350px')
        ->and($stored)->not->toContain('losfastidiosofficial');
});

test('a plain youtube link is stored as a player', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => [['name' => 'The Peekies', 'video' => 'https://www.youtube.com/watch?v=YkyJPHmsJ3s']],
    ]))->assertRedirect(route('dashboard.listings.index'));

    expect(storedLineUp(Listing::sole())[0]['video'])
        ->toContain('youtube-nocookie.com/embed/YkyJPHmsJ3s');
});

test('an embed from an unapproved source is rejected with a message on that artist', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => [
            ['name' => 'Fine', 'video' => 'https://www.youtube.com/watch?v=YkyJPHmsJ3s'],
            ['name' => 'Not fine', 'playlist' => '<iframe src="https://evil.example.com/player"></iframe>'],
        ],
    ]))->assertSessionHasErrors('line_up.1.playlist')
        ->assertSessionDoesntHaveErrors('line_up.0.video');

    expect(Listing::count())->toBe(0);
});

test('the line-up is stored in order, each artist with their own playlist and video', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => [
            ['name' => 'Sun Ra Arkestra', 'playlist' => '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>', 'video' => 'https://youtu.be/abc123'],
            ['name' => 'The Comet Is Coming', 'video' => 'https://vimeo.com/76979871'],
        ],
    ]))->assertRedirect(route('dashboard.listings.index'));

    $lineUp = storedLineUp(Listing::sole());

    expect(array_column($lineUp, 'name'))->toBe(['Sun Ra Arkestra', 'The Comet Is Coming'])
        ->and($lineUp[0]['playlist'])->toContain('bandcamp.com/EmbeddedPlayer/album=1')
        ->and($lineUp[0]['video'])->toContain('youtube-nocookie.com/embed/abc123')
        ->and($lineUp[1]['playlist'])->toBeNull()
        ->and($lineUp[1]['video'])->toContain('player.vimeo.com/video/76979871');
});

test('each artist needs a name, and a playlist or a video', function () {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => [
            ['name' => '', 'video' => 'https://youtu.be/abc123'],
            ['name' => 'Nothing to play'],
        ],
    ]))->assertSessionHasErrors(['line_up.0.name', 'line_up.1.playlist']);

    expect(Listing::count())->toBe(0);
});

test('a line-up holds no more than six artists', function () {
    $user = User::factory()->agreed()->create();
    $artist = ['name' => 'Act', 'video' => 'https://youtu.be/abc123'];

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'line_up' => array_fill(0, 7, $artist),
    ]))->assertSessionHasErrors('line_up');

    expect(Listing::count())->toBe(0);
});

test('saving a listing with no artists clears its line-up', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create([
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds('Old act', null, 'https://youtu.be/abc123'),
    ]);

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload())
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->line_up)->toBeNull();
});

test('the edit form is given the line-up to edit', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create([
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds('Sun Ra Arkestra', null, 'https://youtu.be/abc123'),
    ]);

    $this->actingAs($user)->get(route('dashboard.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lineUp.0.name', 'Sun Ra Arkestra')
            ->where('lineUp.0.playlist', null)
            ->where('lineUp.0.video', fn ($video) => str_contains($video, 'youtube-nocookie.com/embed/abc123')));
});

test('a duplicated listing keeps its line-up', function () {
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create([
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds('Sun Ra Arkestra', null, 'https://youtu.be/abc123'),
    ]);

    $this->actingAs($user)->post(route('dashboard.listings.duplicate', $listing))->assertRedirect();

    $copy = Listing::query()->whereKeyNot($listing->id)->sole();

    expect(array_column(storedLineUp($copy), 'name'))->toBe(['Sun Ra Arkestra']);
});

test('gallery images are stored in the shape nova writes', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)->post(route('dashboard.listings.store'), listingPayload([
        'gallery' => [
            UploadedFile::fake()->image('one.jpg', 2400, 1600),
            UploadedFile::fake()->image('two.jpg', 800, 800),
        ],
        'gallery_captions' => ['On stage', null],
    ]))->assertRedirect(route('dashboard.listings.index'));

    $gallery = json_decode(Listing::sole()->gallery, true);

    expect($gallery)->toHaveCount(2)
        ->and($gallery[0]['layout'])->toBe('image')
        ->and($gallery[0]['key'])->toMatch('/^c[0-9a-f]{15}$/')
        ->and($gallery[0]['attributes']['caption'])->toBe('On stage')
        ->and($gallery[1]['attributes']['caption'])->toBeNull();

    foreach ($gallery as $group) {
        expect($group['attributes']['image'])->toStartWith('gallery/');
        Storage::disk('do')->assertExists($group['attributes']['image']);
    }

    // Oversized uploads are scaled down rather than stored at full size.
    $first = Image::decodeBinary(Storage::disk('do')->get($gallery[0]['attributes']['image']));
    expect(max($first->width(), $first->height()))->toBe(1600);
});

test('a gallery image can be removed and a caption edited', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ]));

    $gallery = json_decode($listing->fresh()->gallery, true);
    [$first, $second] = $gallery;

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery_existing' => [
            ['key' => $first['key'], 'caption' => 'Kept and captioned'],
            ['key' => $second['key'], 'remove' => true],
        ],
    ]));

    $updated = json_decode($listing->fresh()->gallery, true);

    expect($updated)->toHaveCount(1)
        ->and($updated[0]['key'])->toBe($first['key'])
        ->and($updated[0]['attributes']['caption'])->toBe('Kept and captioned');

    Storage::disk('do')->assertExists($first['attributes']['image']);
    Storage::disk('do')->assertMissing($second['attributes']['image']);
});

test('an update that does not mention the gallery leaves it intact', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('one.jpg')],
    ]));

    $before = $listing->fresh()->gallery;

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'title' => 'Title only',
    ]));

    expect($listing->fresh()->gallery)->toBe($before);
});

test('deleting a listing removes its gallery images too', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('one.jpg')],
    ]));

    $paths = collect(json_decode($listing->fresh()->gallery, true))
        ->pluck('attributes.image');

    $this->actingAs($user)->delete(route('dashboard.listings.destroy', $listing));

    foreach ($paths as $path) {
        Storage::disk('do')->assertMissing($path);
    }
});

test('the edit page hands the gallery to the front end already parsed', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('one.jpg')],
        'gallery_captions' => ['A caption'],
    ]));

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Edit')
            ->has('gallery', 1)
            ->where('gallery.0.attributes.caption', 'A caption')
        );
});

/**
 * The browser submits a ticked checkbox as the string "on", not a boolean.
 * These pin the values that actually come off the form, since a rejected
 * checkbox fails validation for the whole request and silently discards
 * every other change in the same save.
 */
test('a gallery image is removed when the checkbox arrives as a browser value', function (string|int|bool $checked) {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('one.jpg')],
    ]));

    $group = json_decode($listing->fresh()->gallery, true)[0];

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload([
            'gallery_existing' => [
                ['key' => $group['key'], 'caption' => '', 'remove' => $checked],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->gallery)->toBeNull();
    Storage::disk('do')->assertMissing($group['attributes']['image']);
})->with(['on', 'true', '1', 1, true]);

test('an unticked remove checkbox keeps the image', function (string|bool|null $unchecked) {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('one.jpg')],
    ]));

    $group = json_decode($listing->fresh()->gallery, true)[0];

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload([
            'gallery_existing' => [
                ['key' => $group['key'], 'caption' => 'Still here', 'remove' => $unchecked],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $updated = json_decode($listing->fresh()->gallery, true);

    expect($updated)->toHaveCount(1)
        ->and($updated[0]['attributes']['caption'])->toBe('Still here');
})->with(['off', 'false', '0', false, null]);

test('removing one image and adding another in the same save does both', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'gallery' => [UploadedFile::fake()->image('old.jpg')],
    ]));

    $old = json_decode($listing->fresh()->gallery, true)[0];

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload([
            'gallery_existing' => [
                ['key' => $old['key'], 'caption' => '', 'remove' => 'on'],
            ],
            'gallery' => [UploadedFile::fake()->image('new.jpg')],
            'gallery_captions' => ['The new one'],
        ]))
        ->assertSessionHasNoErrors();

    $updated = json_decode($listing->fresh()->gallery, true);

    expect($updated)->toHaveCount(1)
        ->and($updated[0]['key'])->not->toBe($old['key'])
        ->and($updated[0]['attributes']['caption'])->toBe('The new one');

    Storage::disk('do')->assertMissing($old['attributes']['image']);
    Storage::disk('do')->assertExists($updated[0]['attributes']['image']);
});

test('the thumbnail remove checkbox accepts the browser value too', function () {
    Storage::fake('do');
    $user = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.listings.update', $listing), listingPayload([
        'thumbnail' => UploadedFile::fake()->image('poster.jpg'),
    ]));

    $stored = $listing->fresh()->thumbnail;

    $this->actingAs($user)
        ->put(route('dashboard.listings.update', $listing), listingPayload([
            'remove_thumbnail' => 'on',
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->thumbnail)->toBeNull();
    Storage::disk('do')->assertMissing($stored);
});

test('a promoter who runs no venue has their one promoter picked for them', function () {
    $user = User::factory()->create();
    Promoter::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Create')
            ->has('promoters', 1)
            ->where('managesVenue', false)
        );
});

test('a promoter who also runs a venue is told so, and can say the event is nobody\'s', function () {
    $user = User::factory()->create();
    Promoter::factory()->for($user)->create();
    Venue::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('promoters', 1)
            ->where('managesVenue', true)
        );
});

test('the edit form carries the venue-owner flag too', function () {
    $user = User::factory()->create();
    Promoter::factory()->for($user)->create();
    Venue::factory()->for($user)->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('managesVenue', true));
});

test('my listings names the promoter alongside the venue', function () {
    $user = User::factory()->agreed()->create();
    $venue = Venue::factory()->create(['name' => 'Lansdown Hall']);
    $promoter = Promoter::factory()->create(['name' => 'Emergence Restock']);

    // Dated apart on purpose: the index orders by start_date, so without
    // this the factory's random dates decide which row lands first.
    Listing::factory()->for($user)->create([
        'venue_id' => $venue->id,
        'promoter_id' => $promoter->id,
        'start_date' => today()->addMonth(),
    ]);
    Listing::factory()->for($user)->create([
        'promoter_id' => null,
        'start_date' => today()->addWeek(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Index')
            ->has('listings', 2)
            ->where('listings.0.promoter.name', 'Emergence Restock')
            ->where('listings.0.venue.name', 'Lansdown Hall')
            ->where('listings.1.promoter', null)
        );
});

/**
 * The listings table says how many people have saved each event, which is
 * the only measure of interest anybody has before the doors open. Counted
 * once per person, so a listing three people saved reads as three.
 */
test('the listings table counts who has saved each event', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $readers = User::factory()->count(3)->create();

    $saved = Listing::factory()->create(['user_id' => $owner->id]);
    $ignored = Listing::factory()->create(['user_id' => $owner->id]);

    $saved->bookmarkedBy()->attach($readers);

    $this->actingAs($owner)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($saved, $ignored) {
            $counts = collect($page->toArray()['props']['listings'])
                ->pluck('bookmarked_by_count', 'id');

            expect($counts[$saved->id])->toBe(3)
                ->and($counts[$ignored->id])->toBe(0);
        });
});

test('the listings table says which events have sold out', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    Listing::factory()->create(['user_id' => $owner->id, 'sold_out' => true]);

    $this->actingAs($owner)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.sold_out', true));
});

/**
 * The list is what is still to come by default. An account that has been
 * listing for a while has far more behind it than ahead of it, and the
 * page is there to work from rather than to look back over.
 */
test('the listings table shows what is coming up', function () {
    $owner = User::factory()->has(Promoter::factory())->create();

    $coming = Listing::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Still To Come',
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Been And Gone',
        'start_date' => today()->subMonths(2),
        'end_date' => today()->subMonth(),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 1)
            ->where('listings.0.id', $coming->id)
            ->where('showingAll', false)
            ->where('pastCount', 1)
        );
});

test('everything is shown when it is asked for', function () {
    $owner = User::factory()->has(Promoter::factory())->create();

    Listing::factory()->create([
        'user_id' => $owner->id,
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->create([
        'user_id' => $owner->id,
        'start_date' => today()->subMonths(2),
        'end_date' => today()->subMonth(),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard.listings.index', ['all' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 2)
            ->where('showingAll', true)
        );
});

test('an event running today is still to come', function () {
    $owner = User::factory()->has(Promoter::factory())->create();

    Listing::factory()->create([
        'user_id' => $owner->id,
        'start_date' => today()->subWeek(),
        'end_date' => today(),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 1)->where('pastCount', 0));
});

test('the edit form is sent a listing\'s dates as days, not as instants', function () {
    $user = User::factory()->agreed()->create();

    // 15 July is British Summer Time, so midnight in London is 23:00 the
    // day before in UTC, which is what the browser used to be told.
    $listing = Listing::factory()->for($user)->create([
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertInertia(fn ($page) => $page
            ->where('listing.start_date', '2026-07-15')
            ->where('listing.end_date', '2026-07-15')
        );
});
