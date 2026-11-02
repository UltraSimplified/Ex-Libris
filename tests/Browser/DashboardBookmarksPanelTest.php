<?php

use App\Models\Listing;
use App\Models\User;

function panelListing(User $user, array $attributes = []): Listing
{
    $listing = Listing::factory()->create([
        'title' => 'Village Fete',
        'slug' => 'village-fete',
        'start_date' => today()->addDay(),
        'event_link' => 'https://tickets.example.com/fete',
        'button_text' => 'Buy tickets',
        ...$attributes,
    ]);

    $user->bookmarks()->attach($listing);

    return $listing;
}

/**
 * Waits for the page to have an answer to a request of its own, so a test
 * can look at what that answer left behind. Asked from out here, a moment at
 * a time, as the test's own server only answers while the test is waiting.
 */
function waitForAnswer(object $page, string $path): void
{
    $answered = "performance.getEntriesByType('resource').some((entry) => entry.name.includes('{$path}') && entry.responseEnd > 0)";

    for ($tries = 0; $tries < 100 && ! $page->script($answered); $tries++) {
        $page->wait(0.05);
    }

    expect($page->script($answered))->toBeTrue();
}

const TICKET_LINK = 'a[href*="tickets.example.com"]';
const HEART = '[aria-pressed]';

test('the panel shows a saved event, and shows nothing with none saved', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertDontSee('Bookmarked listings.')
        ->assertNoJavaScriptErrors();

    panelListing($user);

    visit('/dashboard')
        ->assertSee('Bookmarked listings.')
        ->assertSee('Village Fete')
        ->assertSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('a free event asks whether you are going', function () {
    $user = User::factory()->create();
    panelListing($user, ['ticket_price' => 'Free']);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Going')
        ->assertDontSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('a click on the row opens the event, and a click on the controls stays put', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $this->actingAs($user);

    visit('/dashboard')
        ->click('#attending-'.$listing->id)
        ->assertPathIs('/dashboard')
        ->click('Copy link')
        ->assertPathIs('/dashboard')
        ->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->assertNoJavaScriptErrors();
});

test('ticking saves, swaps out the ticket link, and unticking brings it back', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $checkbox = '#attending-'.$listing->id;
    $this->actingAs($user);

    $page = visit('/dashboard')->assertVisible(TICKET_LINK);

    $page->click($checkbox)
        ->assertAttribute($checkbox, 'aria-checked', 'true')
        ->assertMissing(TICKET_LINK);
    waitForAnswer($page, '/attending');

    $this->assertDatabaseHas('bookmarks', ['listing_id' => $listing->id, 'attending' => true]);

    $page->click($checkbox)
        ->assertAttribute($checkbox, 'aria-checked', 'false')
        ->assertVisible(TICKET_LINK)
        ->assertNoJavaScriptErrors();
});

test('a tick that cannot be saved is taken back', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $checkbox = '#attending-'.$listing->id;
    $this->actingAs($user);

    $page = visit('/dashboard')->assertVisible($checkbox);

    // Gone in another tab, so the request is refused.
    $user->bookmarks()->detach();

    $page->click($checkbox);
    waitForAnswer($page, '/attending');

    $page->assertAttribute($checkbox, 'aria-checked', 'false')
        ->assertVisible(TICKET_LINK);
});

test('going to the event and back keeps a tick', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $checkbox = '#attending-'.$listing->id;
    $this->actingAs($user);

    $page = visit('/dashboard')->click($checkbox);
    waitForAnswer($page, '/attending');

    $page->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->back()
        ->assertPathIs('/dashboard')
        ->assertAttribute($checkbox, 'aria-checked', 'true')
        ->assertMissing(TICKET_LINK)
        ->assertNoJavaScriptErrors();
});

test('removing a bookmark keeps the row, and going to the event and back leaves it removed', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $checkbox = '#attending-'.$listing->id;
    $this->actingAs($user);

    $page = visit('/dashboard')->click(HEART);
    waitForAnswer($page, '/bookmark');

    $this->assertDatabaseCount('bookmarks', 0);

    $page->assertSee('Village Fete')
        ->assertAttribute(HEART, 'aria-pressed', 'false')
        ->assertMissing($checkbox)
        ->assertMissing(TICKET_LINK)
        ->assertSee('Add to calendar')
        ->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->back()
        ->assertPathIs('/dashboard')
        ->assertAttribute(HEART, 'aria-pressed', 'false')
        ->assertNoJavaScriptErrors();
});

test('saving it again brings the checkbox back unticked', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $checkbox = '#attending-'.$listing->id;
    $user->bookmarks()->updateExistingPivot($listing->id, ['attending' => true]);
    $this->actingAs($user);

    $page = visit('/dashboard')
        ->assertAttribute($checkbox, 'aria-checked', 'true')
        ->click(HEART);
    waitForAnswer($page, '/bookmark');

    $page->assertMissing($checkbox)
        ->click(HEART)
        ->assertAttribute($checkbox, 'aria-checked', 'false')
        ->assertVisible(TICKET_LINK)
        ->assertNoJavaScriptErrors();
});

test('the share and calendar links carry the event', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard');

    $message = $page->script(<<<'JS'
        new URL(document.querySelector('a[href^="https://wa.me/"]').href).searchParams.get('text')
        JS);

    expect($message)
        ->toContain('Village Fete at '.$listing->venue->name)
        ->toContain('/listings/'.$listing->id.'/village-fete');

    $page->assertAttribute('a[href$=".ics"]', 'href', '/calendar/listings/'.$listing->id.'.ics')
        ->assertNoJavaScriptErrors();
});

test('copying gives the address, or shows it where the clipboard refuses', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard');

    // A clipboard that refuses, as one can on any phone.
    $page->script(<<<'JS'
        void (navigator.clipboard.writeText = () => Promise.reject(new Error('refused')))
        JS);

    $page->click('Copy link')
        ->assertSee('/listings/'.$listing->id.'/village-fete');

    $page->script(<<<'JS'
        void (navigator.clipboard.writeText = (text) => {
            window.copiedText = text;

            return Promise.resolve();
        })
        JS);

    $page->click('Copy link')->assertSee('Copied');

    expect($page->script('window.copiedText'))->toEndWith('/listings/'.$listing->id.'/village-fete');
});

/**
 * Waits for the panel to have reached the top of the window.
 */
function waitForPanelAtTop(object $page): void
{
    $atTop = "Math.abs(document.getElementById('bookmarked-listings').getBoundingClientRect().top) < 40";

    for ($tries = 0; $tries < 100 && ! $page->script($atTop); $tries++) {
        $page->wait(0.05);
    }

    expect($page->script($atTop))->toBeTrue();
}

const MY_BOOKMARKS = 'a[href="/dashboard#bookmarked-listings"]';

/*
 * One listing, deliberately: the shortest panel there can be still has room
 * beneath it to come up to the top of the window.
 */
test('on the Dashboard, My Bookmarks glides to the panel without fetching anything', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard')->assertVisible(MY_BOOKMARKS);

    $page->script(<<<'JS'
        void (() => {
            const scrollIntoView = Element.prototype.scrollIntoView;

            Element.prototype.scrollIntoView = function (options) {
                window.scrolledWith = options?.behavior;

                return scrollIntoView.call(this, options);
            };
        })()
        JS);

    $page->click(MY_BOOKMARKS);

    waitForPanelAtTop($page);

    expect($page->script('window.scrolledWith'))->toBe('smooth')
        ->and($page->script(<<<'JS'
            performance.getEntriesByType('resource')
                .filter((entry) => new URL(entry.name).pathname === '/dashboard').length
            JS))->toBe(0);

    $page->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('from another page, My Bookmarks opens the Dashboard and glides to the panel', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard/orders')->click(MY_BOOKMARKS)->assertPathIs('/dashboard');

    waitForPanelAtTop($page);

    $page->assertNoJavaScriptErrors();
});

test('on a phone, My Bookmarks closes the menu and glides to the panel', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard')->on()->mobile()
        ->click('[data-sidebar="trigger"]')
        ->click(MY_BOOKMARKS);

    waitForPanelAtTop($page);

    $page->assertMissing('[data-mobile="true"]')
        ->assertNoJavaScriptErrors();
});

test('arriving at the old address, or a link to the panel, still finds it', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    panelListing($user);
    $this->actingAs($user);

    $page = visit('/dashboard/bookmarks')->assertPathIs('/dashboard');

    waitForPanelAtTop($page);

    $page->assertNoJavaScriptErrors();
});

test('My Bookmarks is not offered with nothing saved', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]));

    visit('/dashboard')
        ->assertMissing(MY_BOOKMARKS)
        ->assertNoJavaScriptErrors();
});
