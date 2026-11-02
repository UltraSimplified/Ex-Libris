<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordSiteVisit;
use App\Models\SiteVisit;
use App\Nova\Cards\SiteVisits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Lottery;

const READER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

afterEach(function () {
    Lottery::determineResultNormally();
});

function visitAs(string $userAgent, string $path = '/', string $ip = '203.0.113.7'): void
{
    test()->withHeader('User-Agent', $userAgent)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->get($path);
}

test('a reader counts once a day however many pages they read', function () {
    visitAs(READER);
    visitAs(READER, '/listings');

    expect(SiteVisit::query()->count())->toBe(1)
        ->and(SiteVisit::query()->sole()->visited_on)->toBe(today()->toDateString());
});

test('a page change made through Inertia counts as a visit', function () {
    $version = app(HandleInertiaRequests::class)->version(request());

    $this->withHeaders(['User-Agent' => READER, 'X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->get('/')
        ->assertHeader('X-Inertia');

    expect(SiteVisit::query()->count())->toBe(1);
});

test('the same reader counts again on another day', function () {
    visitAs(READER);

    $this->travel(1)->day();
    visitAs(READER);

    expect(SiteVisit::query()->count())->toBe(2)
        ->and(SiteVisit::query()->distinct()->count('visitor_hash'))->toBe(1);
});

test('another address or another browser is another visitor', function () {
    visitAs(READER);
    visitAs(READER, ip: '203.0.113.8');
    visitAs('Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0');

    expect(SiteVisit::query()->count())->toBe(3);
});

test('the visitor\'s address is not stored', function () {
    visitAs(READER);

    expect(SiteVisit::query()->sole()->visitor_hash)
        ->toHaveLength(64)
        ->not->toContain('203.0.113.7');
});

test('a crawler is not a visit', function (string $userAgent) {
    visitAs($userAgent);

    expect(SiteVisit::query()->count())->toBe(0);
})->with([
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'no user agent' => '',
]);

test('only a page that loaded is a visit', function (string $path) {
    visitAs(READER, $path);

    expect(SiteVisit::query()->count())->toBe(0);
})->with([
    'a missing page' => '/there-is-no-such-page',
    'a redirect' => '/dashboard',
    'the Office' => '/office/login',
]);

test('a successful reply that is not a page is not a visit', function () {
    $request = Request::create('/not-a-page', server: ['HTTP_USER_AGENT' => READER]);

    (new RecordSiteVisit)->terminate($request, response()->json(['ok' => true]));

    expect(SiteVisit::query()->count())->toBe(0);
});

test('a HEAD request, as an uptime monitor sends, is not a visit', function () {
    $this->withHeader('User-Agent', READER)->call('HEAD', '/')->assertOk();

    expect(SiteVisit::query()->count())->toBe(0);
});

test('visits older than the report are cleared', function () {
    Lottery::alwaysWin();

    SiteVisit::factory()->create(['visited_on' => today()->subDays(SiteVisit::DAYS)->toDateString()]);
    $oldestReported = SiteVisit::factory()->create(['visited_on' => today()->subDays(SiteVisit::DAYS - 1)->toDateString()]);

    visitAs(READER);

    expect(SiteVisit::query()->pluck('id'))
        ->toHaveCount(2)
        ->toContain($oldestReported->id);
});

test('a visit that cannot be recorded does not cost the reader the page', function () {
    Schema::drop('site_visits');

    $this->withHeader('User-Agent', READER)->get('/')->assertOk();
});

test('the card has the last seven days in order, quiet days as zero', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->count(2)->create(['visited_on' => '2026-11-02']);
    SiteVisit::factory()->create(['visited_on' => '2026-10-27']);
    SiteVisit::factory()->create(['visited_on' => '2026-10-26']);

    expect((new SiteVisits)->jsonSerialize()['days'])->toBe([
        ['day' => 'Tue 27 Oct', 'visits' => 1],
        ['day' => 'Wed 28 Oct', 'visits' => 0],
        ['day' => 'Thu 29 Oct', 'visits' => 0],
        ['day' => 'Fri 30 Oct', 'visits' => 0],
        ['day' => 'Sat 31 Oct', 'visits' => 0],
        ['day' => 'Sun 1 Nov', 'visits' => 0],
        ['day' => 'Mon 2 Nov', 'visits' => 2],
    ]);
});

test('the card\'s total counts a visitor once across the week and leaves out older visits', function () {
    SiteVisit::factory()->create(['visitor_hash' => 'returning']);
    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_on' => today()->subDay()->toDateString()]);
    SiteVisit::factory()->create(['visitor_hash' => 'once']);
    SiteVisit::factory()->create(['visitor_hash' => 'long-gone', 'visited_on' => today()->subDays(SiteVisit::DAYS)->toDateString()]);

    expect((new SiteVisits)->jsonSerialize()['total'])->toBe(2);
});
