<?php

use App\Models\Category;
use App\Models\Promoter;
use App\Models\User;

/**
 * Adding a listing lands on the list, with the new one on it.
 *
 * Driven through the real form and the real redirect, since what is on the
 * page afterwards is the only thing that matters to the person who added it.
 */
test('a listing added through the form is on the page it lands on', function () {
    $user = User::factory()->agreed()->create();
    Promoter::factory()->create(['user_id' => $user->id]);

    $category = Category::factory()->create();
    $start = now()->addWeeks(3)->format('d/m/Y');

    $this->actingAs($user);

    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#title', 'The Lovely Eggs at Rough Trade')
        ->select('#category_id', (string) $category->id)
        ->fill('#start_date', $start)
        ->fill('#body', 'A very loud evening in Nottingham.')
        ->press('Create listing')
        ->assertPathIs('/dashboard/listings')
        ->assertSee('Listing created.')
        ->assertSee('The Lovely Eggs at Rough Trade')
        ->assertNoJavaScriptErrors();
});
