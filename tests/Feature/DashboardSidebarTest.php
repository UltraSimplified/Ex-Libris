<?php

use App\Http\Controllers\Dashboard\ArticleController;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;

/**
 * Most accounts run one venue or one promoter, so the sidebar names it
 * rather than offering an index to go through, and hangs that venue's or
 * promoter's own listings underneath it. The same page does the work,
 * narrowed by what is asked for.
 */
test('a venue section shows only what is on at that venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['name' => 'The Sub Rooms']);

    $here = Listing::factory()->for($user)->create(['venue_id' => $venue->id]);
    Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.index', ['venue' => $venue->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/listings/Index')
            ->has('listings', 1)
            ->where('listings.0.id', $here->id)
            ->where('venue.name', 'The Sub Rooms')
            ->where('promoter', null)
        );
});

test('a promoter section shows only what is running under it', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['name' => 'Field Records']);

    $theirs = Listing::factory()->for($user)->create(['promoter_id' => $promoter->id]);
    Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.index', ['promoter' => $promoter->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 1)
            ->where('listings.0.id', $theirs->id)
            ->where('promoter.name', 'Field Records')
            ->where('venue', null)
        );
});

/**
 * The narrowing is only ever to something this account runs. Anything
 * else names nothing here, and is ignored rather than filtered on, so a
 * guessed number cannot be used to ask what is on somewhere else.
 */
test('a venue the account does not run narrows nothing', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.listings.index', [
            'venue' => Venue::factory()->create()->id,
            'promoter' => Promoter::factory()->create()->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 1)
            ->where('listings.0.id', $listing->id)
            ->where('venue', null)
            ->where('promoter', null)
        );
});

/**
 * What is behind the "show everything" link is counted within the same
 * narrowing, or a venue with nothing behind it would offer a link to
 * somebody else's back catalogue.
 */
test('the past is counted at that venue alone', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();

    Listing::factory()->for($user)->create(['venue_id' => $venue->id]);
    Listing::factory()->for($user)->create(['start_date' => now()->subMonth()]);

    $this->actingAs($user)
        ->get(route('dashboard.listings.index', ['venue' => $venue->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pastCount', 0));

    $this->actingAs($user)
        ->get(route('dashboard.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pastCount', 1));
});

/**
 * The shape of the sidebar itself: a section per venue and per promoter
 * in place of the two indexes that used to stand for all of them.
 */
test('the sidebar names what the account runs rather than counting it', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)
        ->toContain('page.props.auth.venues.map')
        ->toContain('page.props.auth.promoters.map')
        ->toContain("'promoter',\n                'Promoters',")
        ->toContain("'venue',\n                'Venues',")
        ->not->toContain('Manager')
        ->not->toContain("title: 'Venues'")
        ->not->toContain("title: 'Promoter Pages'");
});

/**
 * The writing band is gone from the sidebar, and the magazine, named Good
 * On Paper, leads the first band rather than the footer, above the
 * bookmarks. The Office follows the bookmarks in that band too.
 */
test('the sidebar leads with the magazine and has no writing band', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)
        ->not->toContain('Writing for GOP')
        ->not->toContain('writes(')
        ->not->toContain("title: 'Magazine'")
        ->toContain("href: 'mailto:alex@goodonpaper.info'")
        ->and(substr_count($sidebar, "title: 'Good On Paper'"))->toBe(1)
        ->and(strpos($sidebar, "title: 'Good On Paper'"))->toBeLessThan(strpos($sidebar, "title: 'My Bookmarks'"))
        ->and(strpos($sidebar, "title: 'My Bookmarks'"))->toBeLessThan(strpos($sidebar, "title: 'Office'"))
        ->and(strpos($sidebar, "title: 'Office'"))->toBeLessThan(strpos($sidebar, 'const footerNavItems'));
});

/**
 * Listings are reached through the venue or promoter they are for.
 * Nothing sends an account that runs neither to a listings page: it asks
 * to speak for a promoter first.
 */
test('the sidebar has no listings entry of its own', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect(substr_count($sidebar, "title: 'Event Listings'"))->toBe(1);
});

test('a band with a heading has its items indented beneath it', function () {
    $nav = file_get_contents(resource_path('js/components/NavMain.vue'));

    expect($nav)
        ->toContain('<SidebarGroupLabel')
        ->toContain('<NavSub :items="items" :under="label" />');
});

/**
 * Compacted to a rail there is no width left to indent anything, so the
 * sub-menu hides itself. A venue's listings are only ever reached from
 * under the venue, so they are drawn as icons of their own rather than
 * going with the indent and leaving no way to the listings at all.
 */
test('what is indented keeps an icon on the compacted rail', function () {
    $sub = file_get_contents(resource_path('js/components/NavSub.vue'));

    expect($sub)
        ->toContain("state.value === 'collapsed' && !isMobile.value")
        ->toContain('<SidebarMenu v-if="compacted">')
        ->toContain('<SidebarMenuSub v-else>');
});

/**
 * Both venues offer Event Listings, and on the rail the indent that said
 * which was whose is gone - so the tooltip names what it belongs to.
 */
test('a compacted entry is named by what it sits under', function () {
    $sub = file_get_contents(resource_path('js/components/NavSub.vue'));
    $nav = file_get_contents(resource_path('js/components/NavMain.vue'));

    expect($sub)->toContain(':tooltip="under ? `${under}: ${item.title}` : item.title"')
        ->and($nav)->toContain(':under="item.title"');
});

/**
 * Each of those four narrows the same page. Nothing lists a writer's own
 * features yet - they are written and edited in the Office - so the page
 * only names the section it is for, which is enough to settle its shape.
 */
test('a writer is shown the section they picked', function (string $section, string $label) {
    $writer = User::factory()->create(['is_writer' => true]);

    $this->actingAs($writer)
        ->get(route('dashboard.articles.index', ['section' => $section]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/articles/Index')
            ->where('section', $label)
        );
})->with([
    ['arts-music', 'Arts + Music'],
    ['interviews', 'Interviews'],
    ['reviews', 'Music Reviews'],
    ['photojournals', 'Photojournals'],
]);

test('a section nobody writes for names nothing', function () {
    $writer = User::factory()->create(['is_writer' => true]);

    $this->actingAs($writer)
        ->get(route('dashboard.articles.index', ['section' => 'gossip']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('section', null));
});

/**
 * Arts and Music are two tags on a feature but one section to write for,
 * so the mapping is written down rather than assumed to be one apiece.
 */
test('arts and music are one section over two tags', function () {
    expect(ArticleController::SECTIONS['arts-music']['tags'])->toBe(['Arts', 'Music'])
        ->and(ArticleController::SECTIONS['reviews']['tags'])->toBe(['Reviews']);
});
