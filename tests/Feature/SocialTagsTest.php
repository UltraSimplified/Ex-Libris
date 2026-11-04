<?php

use App\Models\Issue;
use App\Models\Promoter;
use App\Models\Venue;
use App\Support\SiteMeta;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The content of a social tag, as a crawler reads it from the page.
 */
function socialTag(string $html, string $attribute, string $name): ?string
{
    return preg_match('#<meta '.$attribute.'="'.preg_quote($name, '#').'" content="([^"]*)"#', $html, $match)
        ? html_entity_decode($match[1], ENT_QUOTES)
        : null;
}

test('every public page that has no record of its own is still described for sharing', function (string $path, string $title) {
    Issue::factory()->create(['hero_image' => 'issues/latest.webp']);

    $html = $this->get($path)->assertOk()->getContent();

    expect(socialTag($html, 'property', 'og:title'))->toBe($title)
        ->and(socialTag($html, 'property', 'og:description'))->toBe(SiteMeta::DESCRIPTION)
        ->and(socialTag($html, 'property', 'og:image'))->toBe(Storage::disk('do')->url('issues/latest.webp'))
        ->and(socialTag($html, 'name', 'twitter:card'))->toBe('summary_large_image');
})->with([
    'listings' => ['/listings', 'Listings.'],
    'today' => ['/listings/today', 'Today\'s listings.'],
    'features' => ['/magazine/features', 'Features'],
    'archives' => ['/magazine/archives', 'Archives'],
    'stockists' => ['/magazine/stockists', 'Stockists'],
]);

test('the home page speaks for the site', function () {
    Issue::factory()->create(['hero_image' => 'issues/latest.webp']);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('meta.title', 'Good on Paper')
            ->where('meta.description', SiteMeta::DESCRIPTION)
            ->where('meta.image', Storage::disk('do')->url('issues/latest.webp'))
            ->where('meta.jsonLd.@type', 'WebSite')
        );
});

test('a venue\'s page shares its own picture and description, with its place', function () {
    $venue = Venue::factory()->create([
        'name' => 'The Yard Gallery',
        'address' => 'Witheys Yard, 55 High Street',
        'town' => 'Stroud',
        'postcode' => 'GL5 1AS',
        'cover_photo' => 'venues/yard.webp',
        'description' => '<p>A gallery</p><p>in a yard.</p>',
    ]);
    $venue->forceFill(['latitude' => 51.745548, 'longitude' => -2.216327])->save();

    $html = $this->get(route('Venue', [$venue->id, 'the-yard-gallery']))->assertOk()->getContent();

    expect(socialTag($html, 'property', 'og:title'))->toBe('The Yard Gallery')
        ->and(socialTag($html, 'property', 'og:description'))->toBe('A gallery in a yard.')
        ->and(socialTag($html, 'property', 'og:image'))->toBe(Storage::disk('do')->url('venues/yard.webp'))
        ->and($html)->toContain('"@type":"Place"')
        ->and($html)->toContain('"latitude":51.745548');
});

test('a venue with no description is described by where it is', function () {
    $venue = Venue::factory()->create(['name' => 'This House', 'address' => '1 Long Street', 'town' => 'Dursley', 'description' => null]);

    $html = $this->get(route('Venue', [$venue->id, 'this-house']))->getContent();

    expect(socialTag($html, 'property', 'og:description'))->toBe("What's on at This House, 1 Long Street, Dursley, listed by Good on Paper.");
});

test('a promoter\'s page shares its banner and what it says about itself', function () {
    $promoter = Promoter::factory()->create(['name' => 'Stroud Folk', 'about' => "Folk nights\nin Stroud."]);
    $promoter->forceFill(['banner' => 'promoters/banner.webp', 'logo' => 'promoters/logo.webp'])->save();

    $html = $this->get(route('Promoter', [$promoter->id, 'stroud-folk']))->assertOk()->getContent();

    expect(socialTag($html, 'property', 'og:title'))->toBe('Stroud Folk')
        ->and(socialTag($html, 'property', 'og:description'))->toBe('Folk nights in Stroud.')
        ->and(socialTag($html, 'property', 'og:image'))->toBe(Storage::disk('do')->url('promoters/banner.webp'))
        ->and($html)->toContain('"@type":"Organization"');
});

test('a promoter with no banner shares its logo, and with no about is described by name', function () {
    $promoter = Promoter::factory()->create(['name' => 'Stroud Folk', 'about' => null]);
    $promoter->forceFill(['logo' => 'promoters/logo.webp'])->save();

    $html = $this->get(route('Promoter', [$promoter->id, 'stroud-folk']))->getContent();

    expect(socialTag($html, 'property', 'og:image'))->toBe(Storage::disk('do')->url('promoters/logo.webp'))
        ->and(socialTag($html, 'property', 'og:description'))->toBe("What's on from Stroud Folk, listed by Good on Paper.");
});
