<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Services\Listings\ListingLineUp;

/**
 * Hand the thumbnail picker a file, from inside the page.
 *
 * attach() only offers the browser a path on this machine, which the browser
 * refuses, so the picker is given its file here and told it changed, which is
 * all a real pick amounts to.
 */
function chooseThumbnailScript(): string
{
    return <<<'JS'
        () => {
            const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
            const bytes = Uint8Array.from(atob(png), (character) => character.charCodeAt(0));
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], 'poster.png', { type: 'image/png' }));

            const input = document.querySelector('#thumbnail');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        JS;
}

/**
 * Choosing a thumbnail emptied the description.
 *
 * The text areas were bound with :value, which Vue writes back to the DOM on
 * every render of the component, changed or not. Picking a thumbnail sets the
 * preview, which renders the component, and the text areas were put back to
 * what the server sent: nothing at all, on a new listing.
 *
 * Only a real browser typing into a real form shows it. The page props never
 * change, so nothing a feature test can inspect was ever wrong.
 */
test('choosing a thumbnail leaves the text areas as they were typed', function () {
    Category::factory()->create();

    $this->actingAs(User::factory()->agreed()->create());

    $page = visit(route('dashboard.listings.create', absolute: false))
        ->assertSee('Thumbnail image')
        ->fill('#body', 'A very loud evening in Nottingham.')
        // Opening the panel renders the component too, so the embeds are
        // typed after it and have to survive both that and the thumbnail.
        ->click('Additional media')
        ->click('Add artist')
        ->fill('#line_up_0_name', 'Sun Ra Arkestra')
        ->fill('#line_up_0_playlist', '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>')
        ->fill('#line_up_0_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $page->script(chooseThumbnailScript());

    $page->assertPresent('img[src^="blob:"]')
        ->assertValue('#body', 'A very loud evening in Nottingham.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>')
        ->assertValue('#line_up_0_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->assertNoJavaScriptErrors();
});

/**
 * The other half of the same fix: the text areas now only start with the
 * saved text, so an edit has to still arrive with it and keep it.
 */
test('an existing listing opens with its text and keeps it when a thumbnail is chosen', function () {
    $user = User::factory()->agreed()->create();

    $listing = Listing::factory()->for($user)->create([
        'body' => 'Already written, and saved.',
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds(
            'Sun Ra Arkestra',
            '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=2"></iframe>',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ),
    ]);
    [$artist] = app(ListingLineUp::class)->artists($listing->fresh()->line_up);

    $this->actingAs($user);

    $page = visit(route('dashboard.listings.edit', $listing, absolute: false))
        ->assertValue('#body', 'Already written, and saved.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', $artist['playlist'])
        ->assertValue('#line_up_0_video', $artist['video']);

    $page->script(chooseThumbnailScript());

    $page->assertPresent('img[src^="blob:"]')
        ->assertValue('#body', 'Already written, and saved.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', $artist['playlist'])
        ->assertValue('#line_up_0_video', $artist['video'])
        ->assertNoJavaScriptErrors();
});

/**
 * Days ticked in the calendar of specific days, as the page has them.
 */
function tickedDaysScript(): string
{
    return "[...document.querySelectorAll('[data-day][aria-pressed=\"true\"]')].map((day) => day.dataset.day)";
}

test('the specific days checkbox only appears once the end date is after the start', function () {
    $this->actingAs(User::factory()->agreed()->create());

    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#start_date', '01/06/2026')
        ->fill('#end_date', '01/06/2026')
        ->assertDontSee('Set specific days')
        ->fill('#end_date', '20/06/2026')
        ->assertSee('Set specific days')
        ->assertNoJavaScriptErrors();
});

test('select all then Mon leaves every day of the run but the Mondays', function () {
    $this->actingAs(User::factory()->agreed()->create());

    $page = visit(route('dashboard.listings.create', absolute: false))
        ->fill('#start_date', '12/09/2026')
        ->fill('#end_date', '08/11/2026')
        ->click('Set specific days')
        ->click('Select all')
        ->click('[aria-label="Every Monday"]');

    $page->assertSee('50 of 58 days chosen')
        ->assertAttribute('[data-day="2026-09-14"]', 'aria-pressed', 'false')
        ->assertAttribute('[data-day="2026-09-15"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

test('a weekday button ticks every one of that day in the run and shows as on, and unticks them when all are ticked', function () {
    $this->actingAs(User::factory()->agreed()->create());

    $page = visit(route('dashboard.listings.create', absolute: false))
        ->fill('#start_date', '01/06/2026')
        ->fill('#end_date', '20/06/2026')
        ->click('Set specific days')
        ->click('[aria-label="Every Saturday"]')
        ->click('[aria-label="Every Sunday"]');

    expect($page->script(tickedDaysScript()))->toBe(['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20']);
    $page->assertAttribute('[aria-label="Every Saturday"]', 'aria-pressed', 'true');

    $page->click('[aria-label="Every Saturday"]');

    expect($page->script(tickedDaysScript()))->toBe(['2026-06-07', '2026-06-14']);
    $page->assertAttribute('[aria-label="Every Saturday"]', 'aria-pressed', 'false')
        ->assertAttribute('[aria-label="Every Sunday"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

test('shortening the run leaves out the ticked days it no longer reaches, and says so', function () {
    $user = User::factory()->agreed()->create();
    $quiz = Listing::factory()->for($user)->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $this->actingAs($user);

    visit(route('dashboard.listings.edit', $quiz, absolute: false))
        ->assertSee('4 of 43 days chosen')
        ->fill('#end_date', '05/12/2026')
        ->assertSee('3 of 33 days chosen')
        ->assertSee('1 chosen day is outside the new dates, so will not be saved.')
        ->assertNoJavaScriptErrors();
});

test('widening the run again before saving brings back the days it had left out', function () {
    $user = User::factory()->agreed()->create();
    $quiz = Listing::factory()->for($user)->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $this->actingAs($user);

    visit(route('dashboard.listings.edit', $quiz, absolute: false))
        ->fill('#end_date', '05/12/2026')
        ->assertSee('3 of 33 days chosen')
        ->fill('#end_date', '15/12/2026')
        ->assertSee('4 of 43 days chosen')
        ->assertDontSee('outside the new dates')
        ->assertAttribute('[data-day="2026-12-15"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

test('a pub quiz saved on four Tuesdays runs from the first of them to the last', function () {
    $user = User::factory()->agreed()->create();
    Promoter::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create();

    $this->actingAs($user);

    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#title', 'TEST ENTRY')
        ->select('#category_id', (string) $category->id)
        ->fill('#start_date', '01/11/2026')
        ->fill('#end_date', '24/12/2026')
        ->fill('#body', 'TEST ENTRY')
        ->click('Set specific days')
        ->click('[data-day="2026-11-03"]')
        ->click('[data-day="2026-11-17"]')
        ->click('[data-day="2026-12-01"]')
        ->click('[data-day="2026-12-15"]')
        ->press('Create listing')
        ->assertPathIs('/dashboard/listings')
        ->assertNoJavaScriptErrors();

    $quiz = Listing::sole();

    expect($quiz->start_date->toDateString())->toBe('2026-11-03')
        ->and($quiz->end_date->toDateString())->toBe('2026-12-15')
        ->and($quiz->days->map(fn ($day) => $day->day->toDateString())->all())
        ->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']);
});

test('the calendar of specific days fits a phone without scrolling sideways', function () {
    $this->actingAs(User::factory()->agreed()->create());

    $page = visit(route('dashboard.listings.create', absolute: false))
        ->on()->mobile()
        ->fill('#start_date', '01/06/2026')
        ->fill('#end_date', '20/06/2026')
        ->click('Set specific days');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});
