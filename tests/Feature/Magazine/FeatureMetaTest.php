<?php

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The meta a feature page is rendered with.
 *
 * @return array<string, mixed>
 */
function featurePageMeta(Feature $feature): array
{
    return test()->get(route('feature', [$feature->id, $feature->slug]))
        ->assertOk()
        ->viewData('page')['props']['meta'];
}

test('a feature is shared with its own title, excerpt and cover photo by default', function () {
    $feature = Feature::factory()->create([
        'title' => 'Laurie Lee: The Lost Recordings',
        'excerpt' => '<p>Originally   published in issue 44.</p>',
        'cover_photo' => 'features/cover.webp',
    ]);

    $meta = featurePageMeta($feature);

    expect($meta['title'])->toBe('Laurie Lee: The Lost Recordings')
        ->and($meta['description'])->toBe('Originally published in issue 44.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('features/cover.webp'))
        ->and($meta['url'])->toBe(route('feature', [$feature->id, $feature->slug]))
        ->and($meta['type'])->toBe('article');
});

test('the social title, description and image set in the Office are used when there are any', function () {
    $feature = Feature::factory()->create([
        'title' => 'From the Archives',
        'social_title' => 'The lost Laurie Lee tapes',
        'social_description' => 'Thirty years on, the recordings resurface.',
        'social_image' => 'features/social.jpg',
    ]);

    $meta = featurePageMeta($feature);

    expect($meta['title'])->toBe('The lost Laurie Lee tapes')
        ->and($meta['description'])->toBe('Thirty years on, the recordings resurface.')
        ->and($meta['image'])->toBe(Storage::disk('do')->url('features/social.jpg'));
});

test('a long excerpt is cut short for the share', function () {
    $feature = Feature::factory()->create(['excerpt' => str_repeat('word ', 80)]);

    expect(mb_strlen(featurePageMeta($feature)['description']))->toBeLessThanOrEqual(203);
});

test('the page carries Open Graph and Twitter tags for crawlers that read no script', function () {
    $feature = Feature::factory()->create([
        'social_title' => 'The lost Laurie Lee tapes',
        'social_description' => 'Thirty years on.',
        'social_image' => 'features/social.jpg',
    ]);
    $image = Storage::disk('do')->url('features/social.jpg');

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:title" content="The lost Laurie Lee tapes">', false)
        ->assertSee('<meta property="og:description" content="Thirty years on.">', false)
        ->assertSee('<meta property="og:image" content="'.$image.'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<link rel="canonical" href="'.route('feature', [$feature->id, $feature->slug]).'">', false);
});

test('the page describes itself as an Article in JSON-LD, credited to its writer', function () {
    $writer = User::factory()->writer()->create(['name' => 'Matt Shinn']);
    $tag = Tag::factory()->create(['tag' => 'Interviews']);
    $feature = Feature::factory()->create([
        'title' => 'From the Archives',
        'published' => '2018-11-01',
        'user_id' => $writer->id,
        'tag_id' => $tag->id,
        'cover_photo' => 'features/cover.webp',
    ]);

    $article = featurePageMeta($feature)['jsonLd'];

    expect($article)->toMatchArray([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'From the Archives',
        'datePublished' => '2018-11-01',
        'image' => [Storage::disk('do')->url('features/cover.webp')],
        'author' => ['@type' => 'Person', 'name' => 'Matt Shinn'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Good on Paper', 'url' => url('/')],
        'articleSection' => 'Interviews',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('feature', [$feature->id, $feature->slug])],
    ]);

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type":"Article"', false);
});

test('a feature nobody wrote is credited to Good on Paper in its JSON-LD', function () {
    User::factory()->create(['id' => Feature::GOOD_ON_PAPER_USER_ID, 'name' => 'Good on Paper']);
    $feature = Feature::factory()->unbylined()->create();

    expect(featurePageMeta($feature)['jsonLd']['author'])
        ->toBe(['@type' => 'Organization', 'name' => 'Good on Paper', 'url' => url('/')]);
});

test('the Office has a Social media panel for the three values', function () {
    $panel = collect((new NovaFeature(new Feature))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Panel && $field->name === 'Social media');

    expect($panel)->not->toBeNull()
        ->and(collect($panel->data)->pluck('attribute')->all())->toBe(['social_title', 'social_description', 'social_image']);
});
