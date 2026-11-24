<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;

test('the Office listings card charts what is coming up over the download for the next issue', function () {
    $this->travelTo('2026-09-13');
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $music = Category::factory()->create(['name' => 'Music']);
    Listing::factory()->count(3)->create(['category_id' => $music->id, 'start_date' => '2026-10-04']);

    visit('/office')
        ->assertSee('Upcoming by category')
        ->assertSee('Music · 3 (100%)')
        ->assertSee('Upcoming by month')
        ->assertSee('October · 3 (100%)')
        ->assertSee('Download listings for the October issue')
        ->assertSeeIn('.gop-disk', '3')
        ->assertSee('Download October listings')
        ->assertNoJavaScriptErrors();
});
