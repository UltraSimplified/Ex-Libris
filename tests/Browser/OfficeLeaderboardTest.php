<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;

test('the promoters page lists who has the most coming up above the index', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $promoter = Promoter::factory()->create(['name' => 'Busy Promoter']);
    Listing::factory()->count(2)->create(['promoter_id' => $promoter->id, 'start_date' => today()->addWeek()]);

    visit('/office/resources/promoters')
        ->assertSee('Top 5 Promoters')
        ->assertSee('2 events')
        ->assertNoJavaScriptErrors();
});
