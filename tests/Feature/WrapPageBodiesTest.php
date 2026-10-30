<?php

use App\Models\Page;

/**
 * The Flexible field renders nothing for a raw HTML body and then saves the
 * empty layout set back over it, so opening one of the older pages in Nova
 * would destroy its copy. These pages have to be wrapped before that
 * happens, and wrapping must not touch pages that are already laid out.
 */
test('a raw HTML body is wrapped in a single Page body layout', function () {
    $page = Page::factory()->create(['body' => '<p>Real copy that must survive.</p>']);

    $this->artisan('pages:wrap-bodies --commit')->assertSuccessful();

    $body = json_decode($page->fresh()->body, true);

    expect($body)->toHaveCount(1)
        ->and($body[0]['layout'])->toBe('copy')
        ->and($body[0]['attributes']['text'])->toBe('<p>Real copy that must survive.</p>')
        ->and($body[0]['key'])->toMatch('/^c[0-9a-z]{15}$/i');
});

test('a page already laid out is left exactly as it was', function () {
    $page = Page::factory()->flexible()->create();
    $before = $page->body;

    $this->artisan('pages:wrap-bodies --commit')->assertSuccessful();

    expect($page->fresh()->body)->toBe($before);
});

test('nothing is written without --commit', function () {
    $page = Page::factory()->create(['body' => '<p>Untouched.</p>']);

    $this->artisan('pages:wrap-bodies')->assertSuccessful();

    expect($page->fresh()->body)->toBe('<p>Untouched.</p>');
});

test('--slug limits the pages that are wrapped', function () {
    $wanted = Page::factory()->create(['slug' => 'advertise', 'body' => '<p>Wrap me.</p>']);
    $other = Page::factory()->create(['slug' => 'terms', 'body' => '<p>Leave me.</p>']);

    $this->artisan('pages:wrap-bodies --commit --slug=advertise')->assertSuccessful();

    expect(json_decode($wanted->fresh()->body, true))->toBeArray()
        ->and($other->fresh()->body)->toBe('<p>Leave me.</p>');
});

test('a wrapped body renders the same copy the page served before', function () {
    $page = Page::factory()->create(['slug' => 'advertise', 'body' => '<p>Rates and sizes.</p>']);

    $this->artisan('pages:wrap-bodies --commit')->assertSuccessful();

    $this->get('/advertise')->assertInertia(
        fn ($assert) => $assert->component('Page')
            ->where('page.body.0.attributes.text', '<p>Rates and sizes.</p>')
    );
});
