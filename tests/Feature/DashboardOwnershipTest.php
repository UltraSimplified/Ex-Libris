<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Str;

/**
 * Promoters and venues answer to one account each, granted in Nova. That
 * ownership is what lets somebody edit a listing from the front end, and
 * what decides whether the dashboard offers those sections at all.
 */
test('guests cannot reach the promoters or venues dashboard', function () {
    $this->get(route('dashboard.promoters.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard.venues.index'))->assertRedirect(route('login'));
});

test('an account managing nothing is refused both sections', function () {
    $user = User::factory()->create();

    // Records nobody owns must not fall to the first person who asks.
    Promoter::factory()->create();
    Venue::factory()->create();

    $this->actingAs($user)->get(route('dashboard.promoters.index'))->assertForbidden();
    $this->actingAs($user)->get(route('dashboard.venues.index'))->assertForbidden();
});

/**
 * The sidebar heads a section with each promoter and venue an account
 * runs, so it is handed their names rather than only whether there are
 * any. An account running neither is handed nothing and gets no section.
 */
test('the dashboard is told what the account runs, by name', function () {
    $plain = User::factory()->create();
    $promoterOwner = User::factory()
        ->has(Promoter::factory()->state(['name' => 'Field Records']))
        ->create();
    $venueOwner = User::factory()
        ->has(Venue::factory()->state(['name' => 'The Sub Rooms']))
        ->create();

    $runs = function (User $user): array {
        $props = $this->actingAs($user)->get(route('dashboard.orders.index'))->viewData('page')['props']['auth'];

        return [
            collect($props['promoters'])->pluck('name')->all(),
            collect($props['venues'])->pluck('name')->all(),
        ];
    };

    expect($runs($plain))->toBe([[], []])
        ->and($runs($promoterOwner))->toBe([['Field Records'], []])
        ->and($runs($venueOwner))->toBe([[], ['The Sub Rooms']]);
});

test('the promoters index lists only what the account manages', function () {
    $user = User::factory()->create();
    $mine = Promoter::factory()->for($user)->create();
    Promoter::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.promoters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/promoters/Index')
            ->has('promoters', 1)
            ->where('promoters.0.id', $mine->id)
        );
});

test('the venues index lists only what the account manages', function () {
    $user = User::factory()->create();
    $mine = Venue::factory()->for($user)->create();
    Venue::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.venues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/venues/Index')
            ->has('venues', 1)
            ->where('venues.0.id', $mine->id)
        );
});

test('an owner can edit and save their promoter', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->get(route('dashboard.promoters.edit', $promoter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/promoters/Edit'));

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => 'New Name',
            'email' => 'hello@example.com',
            'brand_colour' => '#ff0000',
        ])
        ->assertRedirect(route('dashboard.promoters.index'));

    expect($promoter->fresh())
        ->name->toBe('New Name')
        ->email->toBe('hello@example.com')
        ->brand_colour->toBe('#ff0000');
});

test('an owner can edit and save their venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['town' => 'Stroud']);

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'town' => 'Nailsworth',
            'description' => 'A barn with a bar.',
            'phone' => '01453 123456',
            'email' => 'bookings@barn.test',
        ])
        ->assertRedirect(route('dashboard.venues.index'));

    expect($venue->fresh())
        ->town->toBe('Nailsworth')
        ->phone->toBe('01453 123456')
        ->email->toBe('bookings@barn.test')
        ->description->toBe('<p>A barn with a bar.</p>');
});

test('a venue\'s email must be an email address', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['email' => null]);

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'email' => 'not an email',
        ])
        ->assertSessionHasErrors('email');

    expect($venue->fresh()->email)->toBeNull();
});

test('a venue description typed in the dashboard is escaped and kept in paragraphs', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create([
        'description' => '<p>Office <strong>words</strong> &amp; more.</p><p>Second<br>line.</p>',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.venues.edit', $venue))
        ->assertInertia(fn ($page) => $page
            ->where('venue.description', "Office words & more.\n\nSecond\nline.")
            ->where('publicUrl', route('Venue', ['id' => $venue->id, 'slug' => Str::slug($venue->name)])));

    $this->actingAs($user)->put(route('dashboard.venues.update', $venue), [
        'name' => $venue->name,
        'description' => "Live <script>alert(1)</script> music.\r\n\r\nBar open\nlate.",
    ]);

    expect($venue->fresh()->description)
        ->toBe('<p>Live &lt;script&gt;alert(1)&lt;/script&gt; music.</p><p>Bar open<br>'."\n".'late.</p>');
});

test('somebody else\'s promoter or venue cannot be reached', function () {
    $user = User::factory()->has(Promoter::factory())->has(Venue::factory())->create();
    $promoter = Promoter::factory()->for(User::factory())->create();
    $venue = Venue::factory()->for(User::factory())->create();

    $this->actingAs($user)->get(route('dashboard.promoters.edit', $promoter))->assertForbidden();
    $this->actingAs($user)->put(route('dashboard.promoters.update', $promoter), ['name' => 'Mine now'])->assertForbidden();
    $this->actingAs($user)->get(route('dashboard.venues.edit', $venue))->assertForbidden();
    $this->actingAs($user)->put(route('dashboard.venues.update', $venue), ['name' => 'Mine now'])->assertForbidden();

    expect($promoter->fresh()->name)->not->toBe('Mine now')
        ->and($venue->fresh()->name)->not->toBe('Mine now');
});

test('an unowned promoter is nobody\'s to edit', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    $orphan = Promoter::factory()->create();

    $this->actingAs($user)->get(route('dashboard.promoters.edit', $orphan))->assertForbidden();
});

test('a save cannot hand the record to somebody else or make a venue a stockist', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();
    $venue = Venue::factory()->for($user)->create(['stockist' => false]);
    $other = User::factory()->create();

    $this->actingAs($user)->put(route('dashboard.promoters.update', $promoter), [
        'name' => $promoter->name,
        'user_id' => $other->id,
    ])->assertRedirect();

    $this->actingAs($user)->put(route('dashboard.venues.update', $venue), [
        'name' => $venue->name,
        'user_id' => $other->id,
        'stockist' => true,
    ])->assertRedirect();

    expect($promoter->fresh()->user_id)->toBe($user->id)
        ->and($venue->fresh()->user_id)->toBe($user->id)
        ->and((bool) $venue->fresh()->stockist)->toBeFalse();
});

test('a promoter owner may edit the listings running under it', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();
    $listing = Listing::factory()->create(['promoter_id' => $promoter->id]);

    expect($user->can('update', $listing))->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertOk();
});

test('a venue owner may edit whatever is on at their venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $listing = Listing::factory()->create([
        'venue_id' => $venue->id,
        'user_id' => User::factory()->create()->id,
    ]);

    expect($user->can('update', $listing))->toBeTrue();
});

test('ownership of one promoter does not reach another promoter\'s listings', function () {
    $user = User::factory()->has(Promoter::factory())->has(Venue::factory())->create();
    $listing = Listing::factory()->create([
        'promoter_id' => Promoter::factory()->create()->id,
    ]);

    expect($user->can('update', $listing))->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertForbidden();
});

test('a listing belonging to the magazine is left to Nova', function () {
    $user = User::factory()->has(Promoter::factory())->has(Venue::factory())->create();
    $listing = Listing::factory()->create([
        'user_id' => null,
        'promoter_id' => null,
        'venue_id' => null,
    ]);

    expect($user->can('update', $listing))->toBeFalse();
});

test('the listings index gathers everything the account can edit', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();
    $venue = Venue::factory()->for($user)->create();

    $own = Listing::factory()->for($user)->create();
    $viaPromoter = Listing::factory()->create(['promoter_id' => $promoter->id]);
    $viaVenue = Listing::factory()->create(['venue_id' => $venue->id]);
    Listing::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Index')
            ->has('listings', 3)
        );

    expect($user->editableListings()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$own->id, $viaPromoter->id, $viaVenue->id])->sort()->values()->all());
});

/**
 * The listing form offers only what the account may actually choose, and
 * the same question is asked again on the way in.
 */
test('the promoter picker offers only the account\'s own promoters', function () {
    $user = User::factory()->create();
    $mine = Promoter::factory()->for($user)->create();
    Promoter::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('promoters', 1)
            ->where('promoters.0.id', $mine->id)
        );
});

/*
 * Only the office and the accounts it has linked to a promoter or venue
 * add listings: there is no queue, so nothing else may.
 */
test('an account with no promoter or venue cannot add a listing', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.listings.create'))
        ->assertForbidden();

    $this->post(route('dashboard.listings.store'), [
        'title' => 'Pub Quiz',
        'body' => 'Every Tuesday.',
        'category_id' => Category::factory()->create()->id,
        'venue_id' => Venue::factory()->create()->id,
        'start_date' => today()->addWeek()->toDateString(),
        'button_text' => 'Find out more',
    ])->assertForbidden();

    expect(Listing::count())->toBe(0);
});

test('a venue owner may only list at their own venues', function () {
    $user = User::factory()->create();
    $mine = Venue::factory()->for($user)->create();
    Venue::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venues', 1)
            ->where('venues.0.id', $mine->id)
        );
});

test('an account with no venues may list anywhere', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    Venue::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('venues', 3));
});

test('a listing keeps the venue and promoter it already has while being edited', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $elsewhere = Venue::factory()->create();
    $theirPromoter = Promoter::factory()->create();

    Promoter::factory()->for($user)->create();

    $listing = Listing::factory()->for($user)->create([
        'venue_id' => $elsewhere->id,
        'promoter_id' => $theirPromoter->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venues', 2)
            ->has('promoters', 2)
        );

    expect($user->listableVenues($listing)->pluck('id')->all())
        ->toContain($venue->id, $elsewhere->id);
});

test('a promoter or venue the account does not manage is refused on save', function () {
    $user = User::factory()->create();
    Venue::factory()->for($user)->create();

    $payload = listingPayload([
        'venue_id' => Venue::factory()->create()->id,
        'promoter_id' => Promoter::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), $payload)
        ->assertSessionHasErrors(['venue_id', 'promoter_id']);

    expect(Listing::where('title', $payload['title'])->exists())->toBeFalse();
});

test('a listing saves against the promoter and venue the account manages', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $promoter = Promoter::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'title' => 'Ours, at ours',
            'venue_id' => $venue->id,
            'promoter_id' => $promoter->id,
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    expect(Listing::where('title', 'Ours, at ours')->first())
        ->venue_id->toBe($venue->id)
        ->promoter_id->toBe($promoter->id);
});

test('a promoter who also runs a venue may list anywhere, their own first', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    $mine = Venue::factory()->for($user)->create(['name' => 'Zed Hall']);
    $lansdown = Venue::factory()->create(['name' => 'Lansdown Hall']);

    $this->actingAs($user)
        ->get(route('dashboard.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Alphabetically Zed Hall would come last; it is theirs, so it leads.
            ->has('venues', 2)
            ->where('venues.0.id', $mine->id)
            ->where('venues.0.owned', true)
            ->where('venues.1.id', $lansdown->id)
            ->where('venues.1.owned', false)
        );
});

test('a promoter may save a listing at a venue they do not run', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    Venue::factory()->for($user)->create();
    $elsewhere = Venue::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), listingPayload([
            'venue_id' => $elsewhere->id,
        ]))
        ->assertRedirect(route('dashboard.listings.index'));

    expect(Listing::latest('id')->first()->venue_id)->toBe($elsewhere->id);
});

test('only a promoter is offered the chance to add a venue', function () {
    $promoter = User::factory()->has(Promoter::factory())->create();
    $venueOwner = User::factory()->create();
    Venue::factory()->for($venueOwner)->create();

    $this->actingAs($promoter)
        ->get(route('dashboard.listings.create'))
        ->assertInertia(fn ($page) => $page->where('canAddVenue', true));

    $this->actingAs($venueOwner)
        ->get(route('dashboard.listings.create'))
        ->assertInertia(fn ($page) => $page->where('canAddVenue', false));
});
