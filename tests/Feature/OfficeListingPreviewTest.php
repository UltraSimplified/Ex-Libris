<?php

use App\Models\Listing;
use App\Models\User;
use App\Nova\Advert as AdvertResource;
use App\Nova\Listing as ListingResource;
use App\Nova\Resource;
use Illuminate\Support\Facades\File;
use Laravel\Nova\Nova;

/**
 * Clicking a row in the Office opens the form, and the eye on a listings
 * row frames the listing as the public will see it rather than showing
 * the read-only detail page. Adverts are the exception: an advert opens to
 * its detail page, where its report and its figures are.
 */
test('every resource in the Office opens to the form when clicked, bar adverts', function () {
    $resources = collect(File::files(app_path('Nova')))
        ->map(fn ($file): string => 'App\\Nova\\'.$file->getFilenameWithoutExtension())
        ->reject(fn (string $class): bool => $class === Resource::class);

    // Guards the glob itself: an empty list would pass the loop below
    // without having checked anything.
    expect($resources)->toContain(ListingResource::class)
        ->and($resources)->toContain(AdvertResource::class);

    $resources->each(function (string $class): void {
        $clickAction = $class === AdvertResource::class ? 'view' : 'edit';

        expect($class::$clickAction)->toBe($clickAction, "{$class} does not open to the {$clickAction} page");
    });
});

test('the click action is set once on the base rather than resource by resource', function () {
    expect(Resource::$clickAction)->toBe('edit');

    // Listing says nothing about it, so this is the base showing through.
    expect(ListingResource::$clickAction)->toBe('edit');
});

/**
 * office-preview.js builds the frame's address from the row's id rather
 * than being handed one, so the shape of the route is what keeps the two
 * in step. Change the route and this fails rather than the eye quietly
 * opening an empty frame.
 */
test('the preview route keeps the shape the office script builds', function () {
    $listing = Listing::factory()->create();

    expect(route('listings.preview', $listing, absolute: false))
        ->toBe('/listings/'.$listing->id.'/preview');
});

test('the office previews a listing on its own', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $listing = Listing::factory()->create();

    $this->actingAs($staff)
        ->get(route('listings.preview', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listing')
            ->where('preview', true)
        );
});

/**
 * With the site's header, footer and links around it the frame is a
 * working copy of the whole site inside the editor - the back arrow alone
 * leads to every listing there is. The flag is what the page reads to
 * leave all of that off.
 */
test('the public listing page is not in preview mode', function () {
    $listing = Listing::factory()->create();

    $this->get(route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listing')
            ->missing('preview')
        );
});

test('the preview is not open to the public', function () {
    $listing = Listing::factory()->create();

    $this->get(route('listings.preview', $listing))->assertRedirect();
});

/**
 * Nova builds the pencil and the eye into its own table row and offers no
 * hook to leave them out, so they are hidden in the stylesheet. Pinned
 * here because a rule that stops matching fails silently - the icons
 * simply come back.
 */
test('the row pencil is hidden now that a click opens the form', function () {
    $css = file_get_contents(public_path('css/nova-overrides.css'));

    expect($css)->toContain("[dusk\$='-edit-button']");
});

test('the row eye is hidden everywhere but on a listing', function () {
    $css = file_get_contents(public_path('css/nova-overrides.css'));

    expect($css)->toContain("[dusk\$='-view-button']:not([href*='/resources/listings/']):not([href*='/resources/features/'])");
});

test('the office serves the script that puts the preview behind the eye', function () {
    $names = collect(Nova::allScripts())->map(fn ($script): string => $script->name());

    expect($names)->toContain('office-preview');
    expect(public_path('js/office-preview.js'))->toBeFile();
});

test('the office serves the stylesheet the row rules live in', function () {
    $names = collect(Nova::allStyles())->map(fn ($style): string => $style->name());

    expect($names)->toContain('admin-overrides');
});

/**
 * The same frame is offered on the dashboard's own listings, where the
 * person looking is the promoter rather than the office.
 */
test('a promoter previews their own listing', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('listings.preview', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listing')
            ->where('preview', true)
        );
});

test('somebody else previews nothing', function () {
    $listing = Listing::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('listings.preview', $listing))
        ->assertForbidden();
});
