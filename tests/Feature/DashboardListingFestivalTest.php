<?php

use App\Models\Category;
use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Rules\FestivalOfPromoter;
use Illuminate\Support\Facades\Storage;

/**
 * A listing joins a festival from Backstage only under the festival's own
 * promoter, and only by an account that runs festivals. Anybody else who
 * may edit it leaves its festival as it was.
 */
function festivalListingPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Riverside Opening Night',
        'body' => 'The first night of the festival.',
        'category_id' => Category::factory()->create()->id,
        'venue_id' => Venue::factory()->create()->id,
        'start_date' => '2027-06-04',
    ], $overrides);
}

test('a festival runner puts a new listing in one of its promoter\'s festivals', function () {
    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();

    $this->actingAs($account)
        ->post(route('dashboard.listings.store'), festivalListingPayload([
            'promoter_id' => $festival->promoter_id,
            'festival_id' => $festival->id,
            'show_festival_mark' => '1',
        ]))
        ->assertSessionHasNoErrors();

    $listing = Listing::sole();

    expect($listing->festival_id)->toBe($festival->id)
        ->and($listing->show_festival_mark)->toBeTrue();
});

test('the form offers each promoter only the festivals the account runs', function () {
    $account = User::factory()->runsFestivals()->create();
    $promoter = Promoter::factory()->for($account)->create();
    $festival = Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027']);
    Festival::factory()->create();

    $this->actingAs($account)
        ->get(route('dashboard.listings.create'))
        ->assertInertia(fn ($page) => $page
            ->where('promoters.0.id', $promoter->id)
            ->where('promoters.0.festivals.0.id', $festival->id)
            ->where('promoters.0.festivals.0.name', 'Riverside Fest 2027')
            ->has('promoters.0.festivals', 1)
            ->where('listing', null));
});

test('a new listing added from a festival starts in it, under its promoter', function () {
    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();

    $this->actingAs($account)
        ->get(route('dashboard.listings.create', ['promoter' => $festival->promoter_id, 'festival' => $festival->id]))
        ->assertInertia(fn ($page) => $page->where('listing', [
            'promoter_id' => $festival->promoter_id,
            'festival_id' => $festival->id,
        ]));

    $this->actingAs($account)
        ->get(route('dashboard.listings.create', ['festival' => Festival::factory()->create()->id]))
        ->assertInertia(fn ($page) => $page->where('listing', null));
});

test('a listing cannot go in another promoter\'s festival', function () {
    $account = User::factory()->runsFestivals()->create();
    $mine = Promoter::factory()->for($account)->create();
    $otherOfMine = Festival::factory()->for(Promoter::factory()->for($account))->create();

    $this->actingAs($account)
        ->post(route('dashboard.listings.store'), festivalListingPayload([
            'promoter_id' => $mine->id,
            'festival_id' => $otherOfMine->id,
        ]))
        ->assertSessionHasErrors(['festival_id' => FestivalOfPromoter::ANOTHER_PROMOTERS]);

    expect(Listing::count())->toBe(0);
});

test('a listing cannot go in a festival with no promoter', function () {
    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();

    $this->actingAs($account)
        ->post(route('dashboard.listings.store'), festivalListingPayload([
            'promoter_id' => '',
            'festival_id' => $festival->id,
        ]))
        ->assertSessionHasErrors(['festival_id' => FestivalOfPromoter::NEEDS_A_PROMOTER]);
});

test('a festival runner cannot put a listing it edits in a festival it does not run', function () {
    $account = User::factory()->runsFestivals()->create();
    $notRun = Festival::factory()->create();
    $listing = Listing::factory()->for($account)->create(['promoter_id' => $notRun->promoter_id]);

    $this->actingAs($account)
        ->put(route('dashboard.listings.update', $listing), festivalListingPayload([
            'promoter_id' => $notRun->promoter_id,
            'festival_id' => $notRun->id,
        ]))
        ->assertSessionHasErrors(['festival_id' => FestivalOfPromoter::NOT_YOURS]);

    expect($listing->fresh()->festival_id)->toBeNull();
});

test('a venue owner editing a festival event at their venue keeps it in the festival', function (bool $runsFestivals) {
    $festival = Festival::factory()->create();
    $venueOwner = User::factory()->create(['runs_festivals' => $runsFestivals]);
    $venue = Venue::factory()->for($venueOwner)->create();
    $listing = Listing::factory()->create([
        'venue_id' => $venue->id,
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
        'show_festival_mark' => false,
    ]);

    $this->actingAs($venueOwner)
        ->put(route('dashboard.listings.update', $listing), festivalListingPayload([
            'venue_id' => $venue->id,
            'promoter_id' => $festival->promoter_id,
            // What its form sends: the festival it is in, offered as kept.
            ...($runsFestivals ? ['festival_id' => $festival->id, 'show_festival_mark' => '0'] : []),
            'event_time_description' => 'Doors 8pm',
        ]))
        ->assertSessionHasNoErrors();

    $listing->refresh();

    expect($listing->event_time_description)->toBe('Doors 8pm')
        ->and($listing->festival_id)->toBe($festival->id)
        ->and($listing->show_festival_mark)->toBeFalse();
})->with([
    'without Runs Festivals' => false,
    'with Runs Festivals, for festivals of its own' => true,
]);

test('an account without Runs Festivals keeps a listing\'s festival and mark, whatever it sends', function () {
    $account = User::factory()->create();
    $promoter = Promoter::factory()->for($account)->create();
    $festival = Festival::factory()->for($promoter)->create();
    $other = Festival::factory()->for($promoter)->create();
    $listing = Listing::factory()->for($account)->create([
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
        'show_festival_mark' => true,
    ]);

    $this->actingAs($account)
        ->put(route('dashboard.listings.update', $listing), festivalListingPayload([
            'promoter_id' => $promoter->id,
            'festival_id' => $other->id,
            'show_festival_mark' => '0',
        ]))
        ->assertSessionHasNoErrors();

    $listing->refresh();

    expect($listing->festival_id)->toBe($festival->id)
        ->and($listing->show_festival_mark)->toBeTrue();
});

test('moving a listing to another promoter takes it out of the festival', function () {
    $account = User::factory()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();
    $elsewhere = Promoter::factory()->for($account)->create();
    $listing = Listing::factory()->for($account)->create([
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
    ]);

    $this->actingAs($account)
        ->put(route('dashboard.listings.update', $listing), festivalListingPayload([
            'promoter_id' => $elsewhere->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->festival_id)->toBeNull()
        ->and($listing->fresh()->promoter_id)->toBe($elsewhere->id);
});

test('a duplicated festival listing stays in the festival with the same mark setting', function () {
    Storage::fake('do');

    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();
    $listing = Listing::factory()->for($account)->create([
        'title' => 'Riverside Opening Night',
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
        'show_festival_mark' => false,
    ]);

    $this->actingAs($account)
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertRedirect();

    $copy = Listing::query()->where('title', 'Copy of Riverside Opening Night')->sole();

    expect($copy->festival_id)->toBe($festival->id)
        ->and($copy->show_festival_mark)->toBeFalse();
});
