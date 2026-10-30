<?php

use App\Models\Issue;
use App\Models\Page;

test('the subscribe button on the home page goes to the subscribe page', function () {
    Issue::factory()->create();
    Page::factory()->create(['title' => 'Subscribe', 'slug' => 'subscribe']);

    visit('/')
        ->click('[data-test="hero-subscribe"]')
        ->assertPathIs('/subscribe')
        ->assertSee('Subscribe')
        ->assertNoJavaScriptErrors();
});
