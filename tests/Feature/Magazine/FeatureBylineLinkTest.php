<?php

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->tag = Tag::factory()->create(['tag' => 'Interviews']);
});

test('a writer on the masthead is bylined with the address of their page', function () {
    $writer = User::factory()->writer()->create(['name' => 'Siân Magill']);
    $feature = Feature::factory()->create(['tag_id' => $this->tag->id, 'user_id' => $writer->id]);

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latest.user.slug', 'sian-magill')
            ->missing('latest.user.is_writer')
        );

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feature.user.slug', 'sian-magill')
        );

    $this->get('/our-writers/sian-magill')->assertOk();
});

test('an account with no page on Our Writers is bylined without one', function () {
    $account = User::factory()->create(['name' => 'Klang Tone Records', 'is_writer' => false]);
    Feature::factory()->create(['tag_id' => $this->tag->id, 'user_id' => $account->id]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featured.0.user.name', 'Klang Tone Records')
            ->where('featured.0.user.slug', null)
        );
});
