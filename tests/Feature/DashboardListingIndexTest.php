<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Venue;

/**
 * Where somebody lands after adding a listing: the list, with the new one on it.
 */
test('a listing that has just been created is on the list it lands on', function (string $start, ?string $end, bool $listed) {
    $user = User::factory()->agreed()->create();

    $this->actingAs($user)
        ->post(route('dashboard.listings.store'), [
            'title' => 'The Lovely Eggs',
            'body' => 'A very loud evening in Nottingham.',
            'category_id' => Category::factory()->create()->id,
            'venue_id' => Venue::factory()->create()->id,
            'start_date' => $start,
            'end_date' => $end,
            'button_text' => 'Find out more',
        ])
        ->assertRedirect(route('dashboard.listings.index'));

    $this->get(route('dashboard.listings.index'))
        ->assertInertia(fn ($page) => $page
            ->has('listings', $listed ? 1 : 0)
            ->where('pastCount', $listed ? 0 : 1)
        );
})->with(fn () => [
    'next week' => [now()->addWeek()->toDateString(), null, true],
    'today' => [today()->toDateString(), null, true],
    'running now, ends next week' => [now()->subWeek()->toDateString(), now()->addWeek()->toDateString(), true],
    'last week, no end date' => [now()->subWeek()->toDateString(), null, false],
]);
