<?php

use App\Models\Listing;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The links a listing's page is given for the addresses in its description.
 *
 * @return list<array{text: string, href: string, external: bool}>
 */
function bodyLinksOn(Listing $listing): array
{
    $links = null;

    test()->get(route('Listing', [$listing->id, $listing->slug ?? 'event']))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$links) {
            $links = $page->toArray()['props']['listing']['body_links'];
        });

    return $links;
}

test('addresses in the description go out tagged as the listing\'s referral, each once', function () {
    $listing = Listing::factory()->create([
        'body' => "Tickets from https://tickets.example.com/show?id=42&utm_source=old or see www.example.com/about\n\n"
            .'Once more: https://tickets.example.com/show?id=42&utm_source=old.',
    ]);

    expect(bodyLinksOn($listing))->toBe([
        [
            'text' => 'https://tickets.example.com/show?id=42&utm_source=old',
            'href' => "https://tickets.example.com/show?id=42&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}",
            'external' => true,
        ],
        [
            'text' => 'www.example.com/about',
            'href' => "https://www.example.com/about?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}",
            'external' => true,
        ],
    ]);
});

test('punctuation closing the sentence is left off an address', function (string $body, string $address) {
    $listing = Listing::factory()->create(['body' => $body]);

    expect(array_column(bodyLinksOn($listing), 'text'))->toBe([$address]);
})->with([
    'a full stop' => ['See https://example.com/fete.', 'https://example.com/fete'],
    'a comma' => ['At https://example.com/fete, from noon', 'https://example.com/fete'],
    'brackets round it' => ['(https://example.com/fete)', 'https://example.com/fete'],
    'a full stop inside brackets' => ['(see https://example.com/fete.)', 'https://example.com/fete'],
    'curly quotes round it' => ['“https://example.com/fete”', 'https://example.com/fete'],
    'a bracket the address opened itself' => ['https://en.wikipedia.org/wiki/Fete_(event).', 'https://en.wikipedia.org/wiki/Fete_(event)'],
]);

test('an address on this site is passed on as typed, to open in place', function () {
    config(['app.url' => 'https://goodonpaper.info']);
    $listing = Listing::factory()->create([
        'body' => 'Our interview: https://www.goodonpaper.info/features/12/interview and the shop at www.goodonpaper.info/shop',
    ]);

    expect(bodyLinksOn($listing))->toBe([
        [
            'text' => 'https://www.goodonpaper.info/features/12/interview',
            'href' => 'https://www.goodonpaper.info/features/12/interview',
            'external' => false,
        ],
        [
            'text' => 'www.goodonpaper.info/shop',
            'href' => 'https://www.goodonpaper.info/shop',
            'external' => false,
        ],
    ]);
});

test('a description with no web address in it gives no links', function (string $body) {
    $listing = Listing::factory()->create(['body' => $body]);

    expect(bodyLinksOn($listing))->toBe([]);
})->with([
    'plain text' => 'Doors at 7.30pm, £5 on the door.',
    'an email address' => 'Email box@example.com for tickets.',
    'an address not yet written' => 'Website coming soon: https://...',
]);

test('the preview is given the description\'s links too', function () {
    $listing = Listing::factory()->create(['body' => 'More at https://example.com/fete']);

    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->get(route('listings.preview', $listing))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listing.body_links.0.text', 'https://example.com/fete')
            ->where('listing.body_links.0.external', true)
        );
});
