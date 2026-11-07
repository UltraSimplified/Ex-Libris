<?php

use App\Models\Issue;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The cover is the largest thing on the home page. Without SSR the browser
 * only meets it once the app has run, so the page names it up front, at the
 * very address the app will ask for, or it would be fetched twice.
 */
test('the home page asks for the latest cover before the app runs', function () {
    Issue::factory()->create(['hero_image' => 'issues/older.webp', 'created_at' => now()->subMonth()]);
    Issue::factory()->create(['hero_image' => 'issues/latest.webp']);

    $coverUrl = rtrim((string) config('filesystems.disks.do.url'), '/').'/issues/latest.webp';

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="preload" as="image" href="'.e($coverUrl).'" fetchpriority="high">', escape: false)
        ->assertDontSee('issues/older.webp', escape: false);
});

test('pages without a cover preload no picture', function () {
    Issue::factory()->create(['hero_image' => 'issues/latest.webp']);

    $this->get('/listings')
        ->assertOk()
        ->assertDontSee('rel="preload" as="image"', escape: false);
});
