<?php

use App\Models\Page;
use App\Nova\Page as NovaPage;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The meta a page is rendered with.
 *
 * @return array<string, mixed>
 */
function sitePageMeta(Page $page): array
{
    return test()->get('/'.$page->slug)->assertOk()->viewData('page')['props']['meta'];
}

test('a page is shared with its own title, the opening of its text and its hero image by default', function () {
    $page = Page::factory()->create([
        'title' => 'About Us',
        'slug' => 'about-us',
        'hero_image' => 'pages/hero.webp',
        'body' => json_encode([
            ['layout' => 'divider', 'key' => 'c1', 'attributes' => []],
            ['layout' => 'copy', 'key' => 'c2', 'attributes' => ['text' => '<p>Good on Paper is   Stroud\'s <strong>arts</strong> magazine.</p>']],
            ['layout' => 'copy', 'key' => 'c3', 'attributes' => ['text' => '<p>A second paragraph.</p>']],
        ]),
    ]);

    $meta = sitePageMeta($page);

    expect($meta['title'])->toBe('About Us')
        ->and($meta['description'])->toBe('Good on Paper is Stroud\'s arts magazine.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('pages/hero.webp'))
        ->and($meta['url'])->toBe(route('page', 'about-us'))
        ->and($meta['type'])->toBe('website');
});

test('an older page of plain HTML is described by its opening words', function () {
    $page = Page::factory()->create(['slug' => 'stockists-old', 'body' => '<h2>Where to find us</h2><p>Shops across Stroud.</p>']);

    expect(sitePageMeta($page)['description'])->toBe('Where to find us Shops across Stroud.');
});

test('a page with no text is described by its title', function () {
    $page = Page::factory()->create(['title' => 'Gallery', 'slug' => 'gallery', 'body' => json_encode([])]);

    expect(sitePageMeta($page)['description'])->toBe('Gallery');
});

test('the social title, description and image set in the Office are used when there are any', function () {
    $page = Page::factory()->create([
        'slug' => 'advertise',
        'social_title' => 'Advertise with Good on Paper',
        'social_description' => 'Reach Stroud every month.',
        'social_image' => 'pages/social.jpg',
    ]);

    $meta = sitePageMeta($page);

    expect($meta['title'])->toBe('Advertise with Good on Paper')
        ->and($meta['description'])->toBe('Reach Stroud every month.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('pages/social.jpg'));
});

test('the page carries Open Graph, Twitter and WebPage tags for crawlers that read no script', function () {
    $page = Page::factory()->create([
        'slug' => 'advertise',
        'social_title' => 'Advertise with Good on Paper',
        'social_description' => 'Reach Stroud every month.',
        'social_image' => 'pages/social.jpg',
    ]);

    $this->get('/advertise')
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta property="og:title" content="Advertise with Good on Paper">', false)
        ->assertSee('<meta property="og:image" content="'.Storage::disk('do')->url('pages/social.jpg').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<link rel="canonical" href="'.route('page', 'advertise').'">', false)
        ->assertSee('"@type":"WebPage"', false);

    expect(sitePageMeta($page)['jsonLd'])->toMatchArray([
        '@type' => 'WebPage',
        'name' => 'Advertise with Good on Paper',
        'url' => route('page', 'advertise'),
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Good on Paper', 'url' => url('/')],
        'publisher' => ['@type' => 'Organization', 'name' => 'Good on Paper', 'url' => url('/')],
    ]);
});

test('the Office has a Social media panel on pages', function () {
    $panel = collect((new NovaPage(new Page))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Panel && $field->name === 'Social media');

    expect($panel)->not->toBeNull()
        ->and(collect($panel->data)->pluck('attribute')->all())->toBe(['social_title', 'social_description', 'social_image'])
        ->and(collect($panel->data)->last()->helpText)->toContain('the hero image');
});
