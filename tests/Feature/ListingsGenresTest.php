<?php

use App\Models\Category;
use App\Models\Listing;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The genre row under the listings sub-nav is filled from what is still to
 * come, so it never offers a genre that could only ever show nothing.
 */
test('the genre row offers each genre with something still to come, in name order', function () {
    $music = Category::factory()->create(['name' => 'Music', 'slug' => 'music']);
    $film = Category::factory()->create(['name' => 'Film', 'slug' => 'film']);
    $quiz = Category::factory()->create(['name' => 'Quiz', 'slug' => 'quiz']);

    Listing::factory()->create(['category_id' => $music->id, 'start_date' => today()->addWeek(), 'end_date' => null]);
    Listing::factory()->create(['category_id' => $film->id, 'start_date' => today()->addMonth(), 'end_date' => null]);
    Listing::factory()->create(['category_id' => $quiz->id, 'start_date' => today()->subWeek(), 'end_date' => null]);

    $this->get('/listings/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('genres.0.slug', 'film')
            ->where('genres.1.slug', 'music')
            ->has('genres', 2)
        );
});
