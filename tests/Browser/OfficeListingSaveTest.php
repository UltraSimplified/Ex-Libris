<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Tests\Browser\Support\ParseMultipartForms;

beforeEach(function () {
    app(Kernel::class)->prependMiddleware(ParseMultipartForms::class);
});

/**
 * Saving a listing in the Office once threw "t.fill is not a function" and
 * wrote nothing: Nova saves a form by calling fill() on every field object
 * it got back from the API, and a custom field drawn without Nova's
 * FormField mixin left it with none. Driving the real form is the only
 * thing that shows that kind of break.
 */
test('a listing can be saved in the Office', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $listing = Listing::factory()->create([
        'title' => 'Giffords Circus: Waterfield',
        'category_id' => Category::factory(),
    ]);

    $this->actingAs($staff);

    /*
     * By its dusk attribute: since Nova 5.7 an input's id carries the form's
     * own title as well ("title-update-listing-giffords-…-text-field"), so
     * "#title" matches nothing and the fill waits for it forever.
     */
    visit("/office/resources/listings/{$listing->id}/edit")
        ->assertSee('Start date')
        ->fill('[dusk="title"]', 'Waterfield, saved from the Office')
        ->click('Update & Continue Editing')
        ->assertNoJavaScriptErrors();

    expect($listing->fresh()->title)->toBe('Waterfield, saved from the Office');
});
