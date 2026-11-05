<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordSiteVisit;
use App\Models\SiteVisit;
use App\Models\User;
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

test('a reader counts once an hour however many pages they read', function () {
    $this->travelTo('2026-11-02 14:35:00');

    visitAs(READER);
    visitAs(READER, '/listings');

    expect(SiteVisit::query()->count())->toBe(1)
        ->and(SiteVisit::query()->sole()->visited_at->toDateTimeString())->toBe('2026-11-02 14:00:00');
});

test('every page the reader asks for counts as a page view', function () {
    visitAs(READER);
    visitAs(READER, '/listings');
    visitAs(READER, '/listings');

    expect(SiteVisit::query()->sole()->requests)->toBe(3);
});

test('a page view is counted against the reader who asked for it', function () {
    visitAs(READER);
    visitAs(READER);
    visitAs(READER, ip: '203.0.113.8');

    expect(SiteVisit::query()->orderBy('id')->pluck('requests')->all())->toBe([2, 1]);
});

test('the time between a reader\'s first and last page is kept', function () {
    $this->travelTo('2026-11-02 14:05:00');
    visitAs(READER);

    $this->travelTo('2026-11-02 14:08:30');
    visitAs(READER, '/listings');

    $visit = SiteVisit::query()->sole();

    expect($visit->first_seen_at->toDateTimeString())->toBe('2026-11-02 14:05:00')
        ->and($visit->last_seen_at->toDateTimeString())->toBe('2026-11-02 14:08:30');
});

test('the Office\'s own staff reading the public site are not counted', function () {
    $this->actingAs(User::factory()->create(['email' => User::OFFICE_EMAILS[0]]));

    visitAs(READER);

    expect(SiteVisit::query()->count())->toBe(0);
});

test('a reader who is signed in but not office staff is counted', function () {
    $this->actingAs(User::factory()->create());

    visitAs(READER);

    expect(SiteVisit::query()->count())->toBe(1);
});

test('a page that did not load is not a page view', function () {
    visitAs(READER);
    visitAs(READER, '/there-is-no-such-page');

    expect(SiteVisit::query()->sole()->requests)->toBe(1);
});

test('a page change made through Inertia counts as a visit', function () {
    $version = app(HandleInertiaRequests::class)->version(request());

    $this->withHeaders(['User-Agent' => READER, 'X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->get('/')
        ->assertHeader('X-Inertia');

    expect(SiteVisit::query()->count())->toBe(1);
});

test('the same reader counts again in another hour', function () {
    visitAs(READER);

    $this->travel(1)->hour();
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

test('visits older than the longest report are cleared', function () {
    Lottery::alwaysWin();

    SiteVisit::factory()->create(['visited_at' => today()->subDays(SiteVisit::DAYS_KEPT)->endOfDay()]);
    $oldestReported = SiteVisit::factory()->create(['visited_at' => today()->subDays(SiteVisit::DAYS_KEPT - 1)]);

    visitAs(READER);

    expect(SiteVisit::query()->pluck('id'))
        ->toHaveCount(2)
        ->toContain($oldestReported->id);
});

test('a visit that cannot be recorded does not cost the reader the page', function () {
    Schema::drop('site_visits');

    $this->withHeader('User-Agent', READER)->get('/')->assertOk();
});

function siteVisitsRange(string $label): array
{
    return collect((new SiteVisits)->jsonSerialize()['ranges'])->firstWhere('label', $label);
}

test('the card offers 24 hours, 7 days and 30 days, in that order', function () {
    expect(collect((new SiteVisits)->jsonSerialize()['ranges'])->pluck('label')->all())
        ->toBe(['24 Hours', '7 Days', '30 Days']);
});

test('the 24 hours run hour by hour up to the one under way, quiet hours as zero', function () {
    $this->travelTo('2026-11-02 14:35:00');

    SiteVisit::factory()->count(2)->create(['visited_at' => '2026-11-02 14:00:00']);
    SiteVisit::factory()->create(['visited_at' => '2026-11-01 15:00:00']);
    SiteVisit::factory()->create(['visited_at' => '2026-11-01 14:00:00']);

    $points = siteVisitsRange('24 Hours')['points'];

    expect($points)->toHaveCount(24)
        ->and($points[0])->toBe(['label' => 'Sun 1 Nov, 3 PM', 'tick' => null, 'visits' => 1, 'requests' => 1])
        ->and($points[1]['visits'])->toBe(0)
        ->and($points[23])->toBe(['label' => 'Mon 2 Nov, 2 PM', 'tick' => null, 'visits' => 2, 'requests' => 2]);
});

test('the 24 hours mark the axis every four hours', function () {
    $this->travelTo('2026-11-02 14:35:00');

    expect(collect(siteVisitsRange('24 Hours')['points'])->pluck('tick')->filter()->values()->all())
        ->toBe(['4 PM', '8 PM', '12 AM', '4 AM', '8 AM', '12 PM']);
});

test('the 7 days count a visitor once a day, quiet days as zero', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-11-02 09:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->create(['visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->create(['visited_at' => '2026-10-27 00:00:00']);
    SiteVisit::factory()->create(['visited_at' => '2026-10-26 23:00:00']);

    expect(siteVisitsRange('7 Days')['points'])->toBe([
        ['label' => 'Tue 27 Oct', 'tick' => 'Tue', 'visits' => 1, 'requests' => 1],
        ['label' => 'Wed 28 Oct', 'tick' => 'Wed', 'visits' => 0, 'requests' => 0],
        ['label' => 'Thu 29 Oct', 'tick' => 'Thu', 'visits' => 0, 'requests' => 0],
        ['label' => 'Fri 30 Oct', 'tick' => 'Fri', 'visits' => 0, 'requests' => 0],
        ['label' => 'Sat 31 Oct', 'tick' => 'Sat', 'visits' => 0, 'requests' => 0],
        ['label' => 'Sun 1 Nov', 'tick' => 'Sun', 'visits' => 0, 'requests' => 0],
        ['label' => 'Mon 2 Nov', 'tick' => 'Mon', 'visits' => 2, 'requests' => 3],
    ]);
});

test('the 30 days run to today and mark the axis counting back from it', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $points = siteVisitsRange('30 Days')['points'];

    expect($points)->toHaveCount(30)
        ->and($points[0]['label'])->toBe('Sun 4 Oct')
        ->and($points[29]['label'])->toBe('Mon 2 Nov')
        ->and(collect($points)->pluck('tick')->filter()->values()->all())
        ->toBe(['9 Oct', '15 Oct', '21 Oct', '27 Oct', '2 Nov']);
});

test('each total counts a visitor once across its range and leaves out older visits', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-11-02 10:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-10-30 10:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'this-week', 'visited_at' => '2026-10-30 10:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'this-month', 'visited_at' => '2026-10-10 10:00:00']);
    SiteVisit::factory()->create(['visitor_hash' => 'long-gone', 'visited_at' => '2026-10-03 23:00:00']);

    expect(siteVisitsRange('24 Hours')['total'])->toBe(1)
        ->and(siteVisitsRange('7 Days')['total'])->toBe(2)
        ->and(siteVisitsRange('30 Days')['total'])->toBe(3);
});

test('each range adds up every page view within it, a visitor counted as often as they read', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-11-02 11:00:00', 'requests' => 4]);
    SiteVisit::factory()->create(['visitor_hash' => 'returning', 'visited_at' => '2026-10-30 10:00:00', 'requests' => 2]);
    SiteVisit::factory()->create(['visitor_hash' => 'long-gone', 'visited_at' => '2026-10-03 23:00:00', 'requests' => 9]);

    expect(siteVisitsRange('24 Hours')['requests'])->toBe(4)
        ->and(siteVisitsRange('7 Days')['requests'])->toBe(6)
        ->and(siteVisitsRange('30 Days')['requests'])->toBe(6);
});

test('a range with nothing in it reports no page views', function () {
    expect(siteVisitsRange('24 Hours')['requests'])->toBe(0);
});

test('each range averages how long its visits lasted', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->lasting(60)->create(['visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->lasting(180)->create(['visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->lasting(600)->create(['visited_at' => '2026-10-30 10:00:00']);

    expect(siteVisitsRange('24 Hours')['averageVisit'])->toBe(120)
        ->and(siteVisitsRange('7 Days')['averageVisit'])->toBe(280);
});

test('a visit of one page has no length to average, and is passed over', function () {
    $this->travelTo('2026-11-02 12:00:00');

    SiteVisit::factory()->create(['visited_at' => '2026-11-02 11:00:00']);
    SiteVisit::factory()->lasting(90)->create(['visited_at' => '2026-11-02 11:00:00']);

    expect(siteVisitsRange('24 Hours')['averageVisit'])->toBe(90);
});

test('a range with nothing that can be timed reports no average', function () {
    SiteVisit::factory()->create();

    expect(siteVisitsRange('24 Hours')['averageVisit'])->toBeNull();
});

test('each range names the dates it covers', function () {
    $this->travelTo('2026-11-02 14:35:00');

    expect(siteVisitsRange('24 Hours')['period'])->toBe('1 November — 2 November')
        ->and(siteVisitsRange('7 Days')['period'])->toBe('27 October — 2 November')
        ->and(siteVisitsRange('30 Days')['period'])->toBe('4 October — 2 November');
});

test('24 hours that fall within one day name it once', function () {
    $this->travelTo('2026-11-02 23:10:00');

    expect(siteVisitsRange('24 Hours')['period'])->toBe('2 November');
});
