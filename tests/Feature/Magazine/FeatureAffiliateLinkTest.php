<?php

use App\Models\Feature;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Keeps these tests independent of whether a Vite manifest has been built.
    $this->withoutVite();
});

/**
 * A feature whose only layout is a full-width image with the given link.
 */
function featureWithImageLink(?string $link): Feature
{
    return Feature::factory()->create([
        'body' => json_encode([
            [
                'layout' => 'imgfull',
                'key' => 'abc123',
                'attributes' => [
                    'image' => 'features/photo.webp',
                    'alt' => 'A photo',
                    'caption' => '',
                    'text' => '',
                    'link' => $link,
                ],
            ],
        ]),
    ]);
}

test('an image link leaves the site with the affiliate querystring', function () {
    $feature = featureWithImageLink('https://www.example.com/book?ref=good');

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feature.body.0.attributes.link', 'https://www.example.com/book?ref=good&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=feature-'.$feature->id)
        );
});

test('an image without a link is left without one', function () {
    $feature = featureWithImageLink(null);

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feature.body.0.attributes.link', null)
        );
});

test('a link with no scheme is sent out as https', function () {
    expect(Feature::factory()->make(['id' => 14])->affiliateUrl('www.example.com/book'))
        ->toBe('https://www.example.com/book?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=feature-14');
});

test('utm parameters the link already carries are replaced, and everything else kept', function () {
    expect(Feature::factory()->make(['id' => 14])->affiliateUrl('https://www.example.com/a?utm_source=old&keep=1&utm_medium=x#top'))
        ->toBe('https://www.example.com/a?keep=1&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=feature-14#top');
});

test('a link that is not a web address is not sent out', function (string $link) {
    expect(Feature::factory()->make(['id' => 14])->affiliateUrl($link))->toBeNull();
})->with(['javascript:alert(1)', 'mailto:someone@example.com', 'ftp://example.com/file', '']);

/**
 * A linked image opens in a new tab, and tells search engines not to follow
 * the link. The three image layouts all do it the same way.
 */
test('the image layouts wrap a linked image in its link', function () {
    foreach (['ImgFull', 'ImgLeft', 'ImgRight'] as $layout) {
        $component = file_get_contents(resource_path("js/components/GOP/layouts/{$layout}.vue"));

        expect($component)
            ->toContain('v-if="props.layout.attributes.link"')
            ->toContain('target="_blank"')
            ->toContain('rel="nofollow noreferrer"');
    }
});
