<?php

use App\Models\Category;
use App\Models\Feature;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('sitemap.xml');
});

test('the sitemap is xml a search engine can read', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
});

test('the sitemap lists what is still to come and leaves out what has been', function () {
    $coming = Listing::factory()->create(['start_date' => today()->addWeek(), 'end_date' => null]);
    $gone = Listing::factory()->create(['start_date' => today()->subWeek(), 'end_date' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.route('Listing', ['id' => $coming->id, 'slug' => $coming->slug]).'</loc>', false)
        ->assertDontSee(route('Listing', ['id' => $gone->id, 'slug' => $gone->slug]));
});

/**
 * The genre pages are the ones a search engine can index: the filters on
 * the listings pages all point it back at the page they filter.
 */
test('the sitemap lists a genre page only while that genre has something on, dated by its latest change', function () {
    $music = Category::factory()->create(['slug' => 'music']);
    $quiz = Category::factory()->create(['slug' => 'quiz']);

    Listing::factory()->create([
        'category_id' => $music->id,
        'start_date' => today()->addWeek(),
        'end_date' => null,
        'updated_at' => '2026-11-01 09:30:00',
    ]);
    Listing::factory()->create(['category_id' => $quiz->id, 'start_date' => today()->subWeek(), 'end_date' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSeeInOrder([
            '<loc>'.route('Category', 'music').'</loc>',
            '<lastmod>2026-11-01T09:30:00+00:00</lastmod>',
        ], false)
        ->assertDontSee(route('Category', 'quiz'));
});

/**
 * Drawn up while somebody from the Office is signed in, the sitemap is
 * kept for everyone after them, so it must not go by what they can see.
 */
test('the sitemap lists published features and leaves out drafts and private ones, whoever asks', function (bool $listed, Closure $make) {
    $feature = $make();
    $office = User::factory()->create(['email' => User::OFFICE_EMAILS[0]]);

    $response = $this->actingAs($office)->get('/sitemap.xml')->assertOk();

    $address = route('feature', ['id' => $feature->id, 'slug' => $feature->slug]);

    $listed ? $response->assertSee($address) : $response->assertDontSee($address);
})->with([
    'published' => [true, fn () => Feature::factory()->create()],
    'a draft' => [false, fn () => Feature::factory()->draft()->create()],
    'private' => [false, fn () => Feature::factory()->madePrivate()->create()],
]);

test('robots.txt points search engines at the sitemap', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://goodonpaper.info/sitemap.xml');
});
