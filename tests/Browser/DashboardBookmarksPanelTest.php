<?php

use App\Models\Listing;
use App\Models\Promoter;
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

/**
 * The Dashboard with the panel in its full layout, which has to be asked for.
 */
function visitFullLayout(): object
{
    return visit('/dashboard')->click('Full');
}

const TICKET_LINK = 'a[href*="tickets.example.com"]';
const HEART = '#bookmarked-listings [aria-pressed]';

test('the panel shows a saved event, and shows nothing with none saved', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertDontSee('Bookmarked listings.')
        ->assertNoJavaScriptErrors();

    panelListing($user);

    visitFullLayout()
        ->assertSee('Bookmarked listings.')
        ->assertSee('Village Fete')
        ->assertSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('a free event asks whether you are going', function () {
    $user = User::factory()->create();
    panelListing($user, ['ticket_price' => 'Free']);
    $this->actingAs($user);

    visitFullLayout()
        ->assertSee('Going')
        ->assertDontSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('the full layout leaves out the price and the promoter, which the listings pages keep', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    $promoter = Promoter::factory()->create(['name' => 'Acme Promotions']);
    $listing = panelListing($user, ['ticket_price' => '£12', 'promoter_id' => $promoter->id]);
    $this->actingAs($user);

    visitFullLayout()
        ->assertSeeIn('#bookmarked-listings', 'Village Fete')
        ->assertSeeIn('#bookmarked-listings', $listing->venue->name)
        ->assertDontSee('Price:')
        ->assertDontSee('Acme Promotions')
        ->assertNoJavaScriptErrors();

    visit('/listings')
        ->assertSee('Village Fete')
        ->assertSee('Price:')
        ->assertSee('Acme Promotions')
        ->assertNoJavaScriptErrors();
});

test('a click on the row opens the event, and a click on the controls stays put', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $this->actingAs($user);

    visitFullLayout()
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

    $page = visitFullLayout()->assertVisible(TICKET_LINK);

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

    $page = visitFullLayout()->assertVisible($checkbox);

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

    $page = visitFullLayout()->click($checkbox);
    waitForAnswer($page, '/attending');

    $page->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->back()
        ->assertPathIs('/dashboard')
        ->assertAttribute($checkbox, 'aria-checked', 'true')
        ->assertMissing(TICKET_LINK)
        ->assertNoJavaScriptErrors();
});

test('removing a bookmark takes its row away, and going to another event and back leaves it gone', function () {
    $user = User::factory()->create();
    panelListing($user);
    $other = panelListing($user, ['title' => 'Harvest Supper', 'slug' => 'harvest-supper', 'start_date' => today()->addDays(2)]);
    $this->actingAs($user);

    $page = visitFullLayout()->click('#bookmarked-listings li:first-child [aria-pressed]');
    waitForAnswer($page, '/bookmark');

    $this->assertDatabaseCount('bookmarks', 1);

    $page->waitForText('Harvest Supper')
        ->wait(0.4)
        ->assertDontSee('Village Fete')
        ->assertSee('Harvest Supper')
        ->click('Harvest Supper')
        ->assertPathIs('/listings/'.$other->id.'/harvest-supper')
        ->back()
        ->assertPathIs('/dashboard')
        ->assertDontSee('Village Fete')
        ->assertSee('Harvest Supper')
        ->assertNoJavaScriptErrors();
});

test('removing the last bookmark says there are none left', function () {
    $user = User::factory()->create();
    panelListing($user);
    $this->actingAs($user);

    $page = visitFullLayout()->click(HEART);
    waitForAnswer($page, '/bookmark');

    $page->waitForText('Nothing bookmarked at the moment.')
        ->wait(0.4)
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
});

test('a bookmark that cannot be removed comes back', function () {
    $user = User::factory()->create();
    panelListing($user);
    $this->actingAs($user);

    $page = visitFullLayout();
    $page->script("window.fetch = () => Promise.resolve(new Response('', { status: 500 }))");

    $page->click(HEART)
        ->wait(0.6)
        ->assertSee('Village Fete')
        ->assertAttribute(HEART, 'aria-pressed', 'true');

    $this->assertDatabaseCount('bookmarks', 1);
});

test('the share and calendar links carry the event', function () {
    $user = User::factory()->create();
    $listing = panelListing($user);
    $this->actingAs($user);

    $page = visitFullLayout();

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

    $page = visitFullLayout();

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

test('My Bookmarks stays in the sidebar on a publisher\'s Gig Widgets', function () {
    $publisher = User::factory()->publisher()->create();
    panelListing($publisher);
    $this->actingAs($publisher);

    visit('/dashboard')
        ->click('Gig Widgets')
        ->assertPathIs('/dashboard/widgets')
        ->assertVisible(MY_BOOKMARKS)
        ->assertNoJavaScriptErrors();
});

test('My Bookmarks is not offered with nothing saved', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]));

    visit('/dashboard')
        ->assertMissing(MY_BOOKMARKS)
        ->assertNoJavaScriptErrors();
});

/**
 * The selector for a day picked out on the calendar.
 */
function calendarDay(DateTimeInterface $day): string
{
    return '[data-day="'.$day->format('Y-m-d').'"]';
}

test('compact shows the picture, title and date, and a click opens the event', function () {
    $user = User::factory()->create();
    $listing = panelListing($user, ['start_date' => '2099-03-05']);
    $this->actingAs($user);

    visitFullLayout()
        ->assertSee('Tickets purchased')
        ->click('Compact')
        ->assertDontSee('Tickets purchased')
        ->assertDontSee('Add to calendar')
        ->assertSee('Village Fete')
        ->assertSee('THURS 5 MAR')
        ->assertVisible('#bookmarked-listings li img')
        ->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->assertNoJavaScriptErrors();
});

test('the panel starts compact, until something else is chosen', function () {
    $user = User::factory()->create();
    panelListing($user);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Village Fete')
        ->assertDontSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('the chosen layout is still chosen after a reload', function () {
    $user = User::factory()->create();
    panelListing($user);
    $this->actingAs($user);

    visitFullLayout()
        ->assertSee('Tickets purchased')
        ->refresh()
        ->assertSee('Village Fete')
        ->assertSee('Tickets purchased')
        ->assertNoJavaScriptErrors();
});

test('the calendar draws only the months with something on, and picks out their days', function () {
    $user = User::factory()->create();
    panelListing($user, ['start_date' => '2099-03-05']);
    panelListing($user, ['title' => 'Harvest Supper', 'slug' => 'harvest-supper', 'start_date' => '2099-06-20']);
    $this->actingAs($user);

    visit('/dashboard')
        ->click('Calendar')
        ->assertSee('March 2099')
        ->assertSee('June 2099')
        ->assertDontSee('April 2099')
        ->assertVisible('[data-day="2099-03-05"]')
        ->assertVisible('[data-day="2099-06-20"]')
        ->assertMissing('[data-day="2099-03-06"]')
        ->assertNoJavaScriptErrors();
});

test('a picked out day opens what is on it, which leads to the event', function () {
    $user = User::factory()->create();
    $listing = panelListing($user, ['start_date' => '2099-03-05']);
    $day = '[data-day="2099-03-05"]';
    $this->actingAs($user);

    visit('/dashboard')
        ->click('Calendar')
        ->assertDontSee('Village Fete')
        ->assertAttribute($day, 'aria-expanded', 'false')
        ->click($day)
        ->assertAttribute($day, 'aria-expanded', 'true')
        ->assertSee('Village Fete')
        ->click($day)
        ->assertDontSee('Village Fete')
        ->click($day)
        ->click('Village Fete')
        ->assertPathIs('/listings/'.$listing->id.'/village-fete')
        ->assertNoJavaScriptErrors();
});

test('a run picks out its days from today to its last, and a listing with no end date its one', function () {
    $user = User::factory()->create();
    panelListing($user, [
        'start_date' => today()->subDays(2),
        'end_date' => today()->addDay(),
    ]);
    panelListing($user, [
        'title' => 'Harvest Supper',
        'slug' => 'harvest-supper',
        'start_date' => today()->addDays(5),
        'end_date' => null,
    ]);
    $this->actingAs($user);

    visit('/dashboard')
        ->click('Calendar')
        ->assertMissing(calendarDay(today()->subDay()))
        ->assertVisible(calendarDay(today()))
        ->assertAttribute(calendarDay(today()), 'aria-current', 'date')
        ->assertVisible(calendarDay(today()->addDay()))
        ->assertMissing(calendarDay(today()->addDays(2)))
        ->assertVisible(calendarDay(today()->addDays(5)))
        ->assertMissing(calendarDay(today()->addDays(6)))
        ->assertNoJavaScriptErrors();
});

test('a bookmark removed in the full layout is gone from the other two', function () {
    $user = User::factory()->create();
    panelListing($user);
    $this->actingAs($user);

    $page = visitFullLayout()->click(HEART);
    waitForAnswer($page, '/bookmark');

    $page->assertSee('Nothing bookmarked at the moment.')
        ->click('Compact')
        ->assertDontSee('Village Fete')
        ->assertSee('Nothing bookmarked at the moment.')
        ->click('Calendar')
        ->assertMissing('[data-day]')
        ->assertSee('Nothing bookmarked at the moment.')
        ->click('Full')
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
});

test('every layout fits a phone without scrolling sideways', function (string $layout) {
    $user = User::factory()->create();
    panelListing($user, ['title' => 'A Very Long Title For A Village Fete That Goes On And On']);
    $this->actingAs($user);

    $page = visit('/dashboard')->on()->mobile()->resize(320, 640)->click($layout);

    $clippedByThePanel = <<<'JS'
        (() => {
            const panel = document.getElementById('bookmarked-listings').getBoundingClientRect();

            return [...document.querySelectorAll('#bookmarked-listings label')]
                .filter((label) => label.getBoundingClientRect().right > panel.right).length;
        })()
        JS;

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($page->script($clippedByThePanel))->toBe(0);

    $page->assertNoJavaScriptErrors();
})->with(['Compact', 'Calendar']);
