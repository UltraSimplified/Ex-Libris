<?php

use App\Models\Listing;

/**
 * Where a listing says when it starts, that goes above its price, on the
 * listings themselves and on the listing's own page.
 */
test('a listing shows its time above its price', function (string $page) {
    $listing = Listing::factory()->create([
        'start_date' => today(),
        'event_time_description' => 'Doors 7pm',
        'ticket_price' => '£12',
    ]);

    $text = visit($page === 'listing' ? route('Listing', [$listing->id, $listing->slug], absolute: false) : '/listings/today')
        ->script('document.body.innerText.toUpperCase()');

    expect($text)->toContain('TIME: DOORS 7PM')
        ->and(strpos($text, 'TIME: DOORS 7PM'))->toBeLessThan(strpos($text, '£12'));
})->with(['listings', 'listing']);

test('a listing with no time leaves the line out', function () {
    $listing = Listing::factory()->create([
        'start_date' => today(),
        'event_time_description' => '  ',
        'ticket_price' => '£12',
    ]);

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee('£12')
        ->assertDontSee('Time:');
});
