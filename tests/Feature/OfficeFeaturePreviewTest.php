<?php

use App\Models\Feature;
use App\Models\User;

/**
 * office-preview.js builds the frame's address from the row's id rather
 * than being handed one, so the shape of the route is what keeps the two
 * in step. Change the route and this fails rather than the eye quietly
 * opening an empty frame.
 */
test('the feature preview route keeps the shape the office script builds', function () {
    $feature = Feature::factory()->create();

    expect(route('features.preview', $feature, absolute: false))
        ->toBe('/magazine/features/'.$feature->id.'/preview');
});

test('the office script frames features as well as listings', function () {
    $script = file_get_contents(public_path('js/office-preview.js'));

    expect($script)
        ->toContain("'/magazine/features/' + id + '/preview'")
        ->toContain("'/listings/' + id + '/preview'");
});

test('staff are shown the feature on its own, in preview mode', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $feature = Feature::factory()->create(['title' => 'Crappy Living: Our Lady of the In Between']);

    $this->actingAs($staff)
        ->get(route('features.preview', $feature))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Magazine/Feature')
            ->where('preview', true)
            ->where('feature.id', $feature->id)
        );
});

/*
 * Taken for a slug, "preview" would show the public page, with the whole
 * site around it, inside the Office's frame.
 */
test('the preview route is reached ahead of the public feature page', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $feature = Feature::factory()->create();

    $this->actingAs($staff)
        ->get('/magazine/features/'.$feature->id.'/preview')
        ->assertInertia(fn ($page) => $page->where('preview', true));
});

test('the public feature page is not in preview mode', function () {
    $feature = Feature::factory()->create();

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug ?? 'a-feature']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Magazine/Feature')
            ->missing('preview')
        );
});

test('the feature preview is only for the office', function () {
    $feature = Feature::factory()->create();

    $this->get(route('features.preview', $feature))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['is_writer' => true]))
        ->get(route('features.preview', $feature))
        ->assertForbidden();
});
