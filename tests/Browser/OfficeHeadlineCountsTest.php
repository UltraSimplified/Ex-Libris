<?php

use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;

test('the Office dashboard shows the venues, events and writers counts in one Statistics card, with no lists', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    Venue::factory()->count(3)->create();
    Promoter::factory()->count(4)->create();
    User::factory()->writer()->count(2)->create();

    visit('/office')
        ->assertSee('Statistics')
        ->assertCount('.gop-count-section', 3)
        ->assertScript("[...document.querySelectorAll('.gop-count-section')].map((section) => section.querySelector('h4').textContent + ' ' + [...section.querySelectorAll('.text-4xl')].map((number) => number.textContent).join(' ')).join(', ')", 'Venues 3 0, Events 4 0 0, Writers 2 0')
        ->assertDontSee('Top 5')
        ->assertNoJavaScriptErrors();
});
