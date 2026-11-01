<?php

use App\Models\Feature;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Keeps these tests independent of whether a Vite manifest has been built.
    $this->withoutVite();
});

test('a feature can be viewed', function () {
    $feature = Feature::factory()->create(['title' => 'The Analogue Revival']);

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.id', $feature->id)
            ->where('title', 'The Analogue Revival')
        );
});

test('the body is decoded from json before it reaches the page', function () {
    $feature = Feature::factory()->create();

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.body.0.layout', 'copy')
        );
});

test('the byline and tag are loaded with the feature', function () {
    $feature = Feature::factory()->create();

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.user.id', $feature->user_id)
            ->where('feature.tag.id', $feature->tag_id)
        );
});

test('the slug segment of the url is ignored when resolving a feature', function () {
    $feature = Feature::factory()->create(['title' => 'The Analogue Revival']);

    // show() looks the record up by id alone, so a mismatched slug still resolves.
    // This test documents current behaviour rather than endorsing it — a canonical
    // redirect would be the more usual approach.
    $this->get(route('feature', [$feature->id, 'a-completely-wrong-slug']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.id', $feature->id)
        );
});

/**
 * A feature with no writer is credited to Good on Paper's own account, so its
 * byline is never blank. Only the byline columns come with it, the same as any
 * other writer, so the account's email never reaches the page.
 */
test('a feature with no writer is credited to Good on Paper', function () {
    User::factory()->create(['id' => Feature::GOOD_ON_PAPER_USER_ID, 'name' => 'Good on Paper']);

    $feature = Feature::factory()->unbylined()->create();

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.user.id', Feature::GOOD_ON_PAPER_USER_ID)
            ->where('feature.user.name', 'Good on Paper')
            ->missing('feature.user.email')
        );
});

test('an unknown feature id returns a 404', function () {
    $this->get(route('feature', [999999, 'does-not-exist']))
        ->assertNotFound();
});

/**
 * The writer's picture is worn as a pin badge, the same way their own
 * dashboard shows it to them, rather than printed flat on the page.
 *
 * A border rather than an outline: an outline is drawn outside the box
 * and would paint over the tin rim and the lift the badge sits on.
 */
test('the byline picture is dressed as a badge', function () {
    $page = file_get_contents(resource_path('js/pages/Magazine/Feature.vue'));

    expect($page)
        ->toContain('<UserAvatar')
        ->toContain('badge')
        ->toContain('border-[3px] border-white')
        ->not->toContain('outline-3 outline-white');
});

/**
 * The writer describes their own picture on their profile, so that is
 * what it is read as - the name is only the fallback.
 */
test('the byline picture is read as the writer described it', function () {
    $page = file_get_contents(resource_path('js/pages/Magazine/Feature.vue'));
    $avatar = file_get_contents(resource_path('js/components/UserAvatar.vue'));

    expect($page)->toContain('props.feature.user.avatar_alt_text')
        ->and($avatar)->toContain(':alt="props.alt || props.user.name"');
});

/**
 * The features index sits inside the same column as every other page: a
 * rule down the left, and the content held off it by the width of the
 * navigation. The wrapper only holds the content off; each section draws
 * its own stretch of the rule, so a section left off the page takes its
 * stretch with it rather than leaving a rule beside nothing.
 */
test('the features index draws the rule down its left edge', function () {
    $page = file_get_contents(resource_path('js/pages/Magazine/Features.vue'));

    expect($page)->toContain('<div class="md:w-full md:pl-48">');

    foreach (['Interviews', 'Advert', 'MoreFeatures', 'Subscribe'] as $section) {
        expect(file_get_contents(resource_path("js/components/GOP/{$section}.vue")))
            ->toContain('md:border-l');
    }
});

/**
 * </DefaultLayout>> left a second root node on the page, rendered as a
 * stray angle bracket under the footer.
 */
test('the features index closes its layout once', function () {
    $page = file_get_contents(resource_path('js/pages/Magazine/Features.vue'));

    expect($page)
        ->toContain('</DefaultLayout>')
        ->not->toContain('</DefaultLayout>>')
        ->and(substr_count($page, '</template>'))->toBe(1);
});

/**
 * Side by side, the subscribe block's cover and form come to a fixed
 * 800px that neither half will give up. The page holds its content off a
 * 192px left margin, so asking for that at md put 800px into a 576px
 * column - and being centred, it spilled over both edges at once and took
 * the form off the right.
 *
 * The turn to a row waits until there is room for it. Below that the
 * cover sits above the form.
 */
test('the subscribe block stacks until there is room to sit side by side', function () {
    $subscribe = file_get_contents(resource_path('js/components/GOP/Subscribe.vue'));

    expect($subscribe)
        ->toContain('flex-col')
        ->toContain('lg:flex-row')
        ->toContain('lg:w-80')
        ->toContain('lg:hidden')
        ->toContain('lg:block')
        ->not->toContain('md:flex-row');
});

/**
 * Stacked, the cover and the form were still 320px apiece and gave
 * nothing back, so a window narrower than that - a small phone, a split
 * screen, a zoomed-in page - was pushed wider than itself. The hamburger
 * is positioned against the window rather than the page, so it went off
 * the edge with it.
 *
 * Both take the width they are given now, and the block centres in what
 * is left until it reaches the 480px the cover keeps once the row forms,
 * so the two layouts meet at the same size. It holds the page's 20px left
 * gutter, and at lg none on the right: the row needs 800px of the 830 the
 * column has, and 20 either side would push it over.
 */
test('the subscribe block gives way on a narrow screen', function () {
    $subscribe = file_get_contents(resource_path('js/components/GOP/Subscribe.vue'));

    expect($subscribe)
        ->toContain('w-full max-w-120')
        ->toContain('w-full lg:hidden')
        ->toContain('w-full bg-white p-6')
        ->toContain('px-5 py-10')
        ->toContain('lg:pr-0')
        ->not->toContain('max-w-100');
});

/**
 * The email field had a width of its own - the browser's default of
 * roughly twenty characters - which took no notice of the panel holding
 * it.
 */
test('the email field is as wide as the form holding it', function () {
    $subscribe = file_get_contents(resource_path('js/components/GOP/Subscribe.vue'));

    expect($subscribe)->toContain('mt-6 w-full rounded-xl');
});

/**
 * The page shows a playlist as it is stored, because it is cleaned when the
 * feature is saved. Whatever reaches the page here is what Playlist.vue draws.
 */
test('a playlist layout reaches the page with its embed already cleaned', function () {
    $feature = Feature::factory()->create([
        'body' => json_encode([
            [
                'layout' => 'playlist',
                'key' => 'abc123',
                'attributes' => [
                    'playlist' => '<iframe style="width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=1/" seamless><a href="https://artist.bandcamp.com/album/one">One</a></iframe>',
                ],
            ],
        ]),
    ]);

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Feature')
            ->where('feature.body.0.layout', 'playlist')
            ->where('feature.body.0.attributes.playlist', fn (string $playlist): bool => str_contains($playlist, 'width: 100%')
                && ! str_contains($playlist, 'seamless')
                && ! str_contains($playlist, '<a '))
        );
});
