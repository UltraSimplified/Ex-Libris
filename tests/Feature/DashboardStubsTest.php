<?php

use App\Models\Order;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\File;

/**
 * My orders and My bookmarks hold their place in the interface for every
 * account. These tests fix the routes and components so the shape does
 * not drift.
 */
test('guests cannot reach any of them', function () {
    $this->get(route('dashboard.orders.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard.bookmarks.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard.adverts.index'))->assertRedirect(route('login'));
});

test('any signed-in account gets both sections', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/orders/Index'));

    $this->actingAs($user)
        ->get(route('dashboard.bookmarks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/bookmarks/Index'));
});

test('adverts are for the people who advertise', function () {
    $promoterOwner = User::factory()->has(Promoter::factory())->create();
    $venueOwner = User::factory()->has(Venue::factory())->create();

    foreach ([$promoterOwner, $venueOwner] as $advertiser) {
        $this->actingAs($advertiser)
            ->get(route('dashboard.adverts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard/adverts/Index'));
    }
});

test('an account that advertises with nobody is refused', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.adverts.index'))
        ->assertForbidden();
});

test('staff can see what an advertiser sees', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->get(route('dashboard.adverts.index'))
        ->assertOk();
});

/**
 * Orders is offered only to an account that has paid for something, so
 * nobody is sent to a page of empty headings - and an order abandoned on
 * Stripe's page does not count.
 */
test('an account is told it has orders once it has paid for one', function () {
    $user = User::factory()->create(['is_promoter' => true]);
    Order::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.hasOrders', false));

    Order::factory()->for($user)->paid()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.hasOrders', true));
});

test('the sidebar offers orders only to an account that has some', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)->toContain('page.props.auth.hasOrders');
});

/**
 * Every layout a page reaches for has to be where the page looks for it.
 * A layout moved out from under its pages breaks nothing until the
 * bundler is run, which is exactly the sort of break that arrives late.
 */
test('every layout a page imports is where the page imports it from', function () {
    $pages = collect(File::allFiles(resource_path('js/pages')))
        ->filter(fn ($file): bool => $file->getExtension() === 'vue');

    expect($pages)->not->toBeEmpty();

    $pages->each(function ($file): void {
        preg_match_all(
            "/from '(\.[^']*layouts\/[^']+\.vue)'/i",
            $file->getContents(),
            $matches,
        );

        foreach ($matches[1] as $import) {
            expect($file->getPath().'/'.$import)
                ->toBeFile($file->getFilename().' imports a layout that is not there: '.$import);
        }
    });
});
