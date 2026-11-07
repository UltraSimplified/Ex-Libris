<?php

use App\Models\Category;
use App\Models\Listing;

/**
 * The genres are chosen in the page, over listings that have already
 * arrived, so only a browser can see what they narrow the list to.
 */
beforeEach(function () {
    $music = Category::factory()->create(['name' => 'Music', 'slug' => 'music']);
    $film = Category::factory()->create(['name' => 'Film', 'slug' => 'film']);
    $quiz = Category::factory()->create(['name' => 'Quiz', 'slug' => 'quiz']);

    Listing::factory()->create(['title' => 'Jazz Tonight', 'category_id' => $music->id, 'start_date' => today(), 'end_date' => null]);
    Listing::factory()->create(['title' => 'Cinema Tonight', 'category_id' => $film->id, 'start_date' => today(), 'end_date' => null]);
    Listing::factory()->create(['title' => 'Pub Quiz Tonight', 'category_id' => $quiz->id, 'start_date' => today(), 'end_date' => null]);
    Listing::factory()->create(['title' => 'Jazz Next Month', 'category_id' => $music->id, 'start_date' => today()->addMonth(), 'end_date' => null]);
});

test('a genre chosen stays chosen from one set of dates to the next', function () {
    visit('/listings/today')
        ->assertSee('Jazz Tonight')
        ->assertSee('Cinema Tonight')
        ->click('[aria-label="Genre"] button:has-text("Music")')
        ->wait(0.5)
        ->assertSee('Jazz Tonight')
        ->assertDontSee('Cinema Tonight')
        ->assertQueryStringHas('genre', 'music')
        ->click('a[href="/listings/?genre=music"]')
        ->assertPathIs('/listings/')
        ->assertSee('Jazz Tonight')
        ->assertSee('Jazz Next Month')
        ->assertDontSee('Cinema Tonight')
        ->click('[aria-label="Genre"] button:has-text("All")')
        ->wait(0.5)
        ->assertSee('Cinema Tonight')
        ->assertQueryStringMissing('genre')
        ->assertNoJavaScriptErrors();
});

test('several genres can be chosen at once, and each taken off again', function () {
    visit('/listings/today')
        ->click('[aria-label="Genre"] button:has-text("Film")')
        ->click('[aria-label="Genre"] button:has-text("Quiz")')
        ->wait(0.5)
        ->assertSee('Cinema Tonight')
        ->assertSee('Pub Quiz Tonight')
        ->assertDontSee('Jazz Tonight')
        ->assertQueryStringHas('genre', 'film,quiz')
        ->click('[aria-label="Genre"] button:has-text("Film")')
        ->wait(0.5)
        ->assertSee('Pub Quiz Tonight')
        ->assertDontSee('Cinema Tonight')
        ->click('[aria-label="Genre"] button:has-text("Quiz")')
        ->wait(0.5)
        ->assertSee('Jazz Tonight')
        ->assertSee('Cinema Tonight')
        ->assertQueryStringMissing('genre')
        ->assertNoJavaScriptErrors();
});

test('the tag on a listing narrows the page it is on to that genre alone', function () {
    visit('/listings/today?genre=film,quiz')
        ->click('.divide-silver a[href$="?genre=quiz"]')
        ->wait(0.5)
        ->assertPathIs('/listings/today')
        ->assertQueryStringHas('genre', 'quiz')
        ->assertSee('Pub Quiz Tonight')
        ->assertDontSee('Cinema Tonight')
        ->assertNoJavaScriptErrors();
});
