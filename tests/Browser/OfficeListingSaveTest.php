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

/**
 * The days ticked on the Office's calendar, as the page has them.
 */
function officeTickedDays(): string
{
    return "[...document.querySelectorAll('[dusk=\"specific_days-specific-days\"] [data-day][aria-pressed=\"true\"]')].map((day) => day.dataset.day)";
}

test('specific days can be ticked and saved in the Office', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $festival = Listing::factory()->create([
        'title' => 'Weekend festival',
        'category_id' => Category::factory(),
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-20',
    ]);

    visit("/office/resources/listings/{$festival->id}/edit")
        ->click('[dusk="set-specific-days"]')
        ->click('[aria-label="Every Saturday"]')
        ->click('[aria-label="Every Sunday"]')
        ->assertSee('5 of 20 days chosen')
        ->click('Update & Continue Editing')
        ->assertNoJavaScriptErrors();

    $festival->refresh();

    expect($festival->start_date->toDateString())->toBe('2026-06-06')
        ->and($festival->end_date->toDateString())->toBe('2026-06-20')
        ->and($festival->days->map(fn ($day) => $day->day->toDateString())->all())
        ->toBe(['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20']);
});

test('the Office calendar opens with a listing\'s days ticked, its dates still editable', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create([
        'title' => 'Pub Quiz',
        'category_id' => Category::factory(),
    ]);

    $page = visit("/office/resources/listings/{$quiz->id}/edit")
        ->assertSee('4 of 43 days chosen');

    expect($page->script(officeTickedDays()))->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15'])
        ->and($page->script("document.querySelector('[dusk=\"start_date\"]').disabled"))->toBeFalse()
        ->and($page->script("document.querySelector('[dusk=\"end_date\"]').disabled"))->toBeFalse();
    $page->assertNoJavaScriptErrors();
});

test('changing the end date in the Office redraws the calendar and leaves out the days it no longer reaches', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create([
        'title' => 'Pub Quiz',
        'category_id' => Category::factory(),
    ]);

    visit("/office/resources/listings/{$quiz->id}/edit")
        ->assertSee('4 of 43 days chosen')
        ->fill('[dusk="end_date"]', '2026-12-05')
        ->assertSee('3 of 33 days chosen')
        ->assertSee('1 chosen day is outside the new dates, so will not be saved.')
        ->assertNoJavaScriptErrors();
});
