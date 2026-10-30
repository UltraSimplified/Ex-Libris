<?php

use App\Models\Listing;
use App\Models\Venue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The link a listing's page sends readers out on, as the page is given it.
 */
function ticketLinkOn(Listing $listing): ?string
{
    $link = null;

    test()->get(route('Listing', [$listing->id, $listing->slug ?? 'event']))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$link) {
            $link = $page->toArray()['props']['listing']['ticket_link'] ?? null;
        });

    return $link;
}

test('an event link keeps its own query and gains Good on Paper\'s referral', function () {
    $listing = Listing::factory()->create([
        'event_link' => 'https://tickets.example.com/show?id=42&utm_source=old#seats',
    ]);

    expect(ticketLinkOn($listing))->toBe(
        "https://tickets.example.com/show?id=42&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}#seats"
    );
});

test('an event link with no address scheme is tagged as a web address', function () {
    $listing = Listing::factory()->create(['event_link' => 'www.example.com/book']);

    expect(ticketLinkOn($listing))->toBe(
        "https://www.example.com/book?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}"
    );
});

test('a listing with no event link sends readers to its venue, tagged', function () {
    $venue = Venue::factory()->create(['url' => 'https://venue.example.com/']);
    $listing = Listing::factory()->create(['event_link' => null, 'venue_id' => $venue->id]);

    expect(ticketLinkOn($listing))->toBe(
        "https://venue.example.com/?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}"
    );
});

test('a link that is not a web address is passed on as it was', function () {
    $listing = Listing::factory()->create(['event_link' => 'mailto:box@example.com']);

    expect(ticketLinkOn($listing))->toBe('mailto:box@example.com');
});
