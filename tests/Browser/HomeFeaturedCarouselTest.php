<?php

use App\Models\Feature;
use App\Models\Issue;
use App\Models\Tag;

const FEATURED = 'section[aria-label="Featured"]';

/**
 * Where the featured track has scrolled to, in articles from the first.
 */
const FEATURED_POSITION = <<<'JS'
    (() => {
        const track = document.querySelector('section[aria-label="Featured"] ul');

        return Math.round(track.scrollLeft / track.clientWidth);
    })()
    JS;

beforeEach(function () {
    Issue::factory()->create();
    $this->featuresTag = Tag::factory()->create(['tag' => 'Interviews']);
});

/**
 * Featured articles, the first the newest, titled so they can be told apart.
 *
 * @param  list<string>  $titles
 */
function featuredArticles(Tag $tag, array $titles): void
{
    foreach ($titles as $age => $title) {
        Feature::factory()->featured()->create([
            'tag_id' => $tag->id,
            'title' => $title,
            'created_at' => now()->subDays($age),
        ]);
    }
}

test('several featured articles scroll along with the arrows, round and round', function () {
    featuredArticles($this->featuresTag, ['First featured', 'Second featured', 'Third featured']);

    visit('/')
        ->assertCount(FEATURED.' ul > li', 3)
        ->assertSee('First featured')
        ->assertAttribute('[aria-label="Show featured article 1"]', 'aria-current', 'true')
        ->click('[aria-label="Next featured article"]')
        ->wait(1)
        ->assertScript(FEATURED_POSITION, 1)
        ->assertAttribute('[aria-label="Show featured article 2"]', 'aria-current', 'true')
        ->click('[aria-label="Next featured article"]')
        ->wait(1)
        ->assertScript(FEATURED_POSITION, 2)
        ->click('[aria-label="Next featured article"]')
        ->wait(1)
        ->assertScript(FEATURED_POSITION, 0)
        ->click('[aria-label="Previous featured article"]')
        ->wait(1)
        ->assertScript(FEATURED_POSITION, 2)
        ->assertAttribute('[aria-label="Show featured article 3"]', 'aria-current', 'true')
        ->assertNoJavaScriptErrors();
});

test('a dot scrolls straight to its featured article', function () {
    featuredArticles($this->featuresTag, ['First featured', 'Second featured', 'Third featured']);

    visit('/')
        ->click('[aria-label="Show featured article 3"]')
        ->wait(1)
        ->assertScript(FEATURED_POSITION, 2)
        ->assertAttribute('[aria-label="Show featured article 3"]', 'aria-current', 'true')
        ->assertNoJavaScriptErrors();
});

test('one featured article sits on its own, with no arrows or dots', function () {
    featuredArticles($this->featuresTag, ['Only featured']);

    visit('/')
        ->assertSee('Only featured')
        ->assertCount(FEATURED.' ul > li', 1)
        ->assertMissing('[aria-label="Next featured article"]')
        ->assertMissing('[aria-label="Show featured article 1"]')
        ->assertNoJavaScriptErrors();
});

test('with nothing featured, the newest feature leads on its own', function () {
    Feature::factory()->create(['tag_id' => $this->featuresTag->id, 'title' => 'Older feature', 'created_at' => now()->subDay()]);
    Feature::factory()->create(['tag_id' => $this->featuresTag->id, 'title' => 'Newest feature', 'created_at' => now()]);

    visit('/')
        ->assertCount(FEATURED.' ul > li', 1)
        ->assertSeeIn(FEATURED, 'Newest feature')
        ->assertDontSeeIn(FEATURED, 'Older feature')
        ->assertSee('Older feature')
        ->assertMissing('[aria-label="Next featured article"]')
        ->assertNoJavaScriptErrors();
});
