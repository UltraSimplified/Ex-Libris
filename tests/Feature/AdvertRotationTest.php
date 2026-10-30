<?php

use App\Models\Advert;
use App\Nova\Actions\MakeLive;
use App\Services\Adverts\AdvertPool;
use App\Services\Adverts\AdvertRotation;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Fields\ActionFields;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * A visitor to the site: a request with a session of its own, kept from
 * one of their page views to the next.
 */
function aVisitor(): Request
{
    $request = Request::create('/');
    $request->setLaravelSession(new Store('gop_session', new ArraySessionHandler(120)));

    return $request;
}

/**
 * The id of the advert the visitor's next page view is given.
 */
function advertForPageView(Request $visitor): ?int
{
    return app(AdvertRotation::class)->next($visitor)['id'] ?? null;
}

/**
 * Whether any two page views in a row showed the same advert.
 *
 * @param  list<int|null>  $seen
 */
function sawTheSameAdvertTwiceRunning(array $seen): bool
{
    foreach (array_slice($seen, 1) as $index => $id) {
        if ($id === $seen[$index]) {
            return true;
        }
    }

    return false;
}

/**
 * The sponsorship a page view is given for its panel, as the page carries it.
 *
 * @return array<string, mixed>|null
 */
function advertOnAPageView(): ?array
{
    return test()->get(route('home'))->assertOk()->viewData('page')['props']['advert'];
}

function makeAdvertsLive(Advert ...$adverts): void
{
    (new MakeLive)->handle(new ActionFields(collect(), collect()), collect($adverts));
}

test('every live sponsorship is given the panel\'s page views, and no other advert is', function () {
    $first = Advert::factory()->sponsorship()->create();
    $second = Advert::factory()->sponsorship()->create();
    Advert::factory()->sponsorship()->create(['active' => false]);
    Advert::factory()->sponsorship()->create(['run_from' => now()->subMonths(2), 'run_to' => now()->subDay()]);
    Advert::factory()->sponsorship()->create(['run_from' => now()->addDay(), 'run_to' => now()->addMonth()]);
    Advert::factory()->create();

    $served = collect(range(1, 40))->map(fn () => advertOnAPageView()['id'])->unique()->sort()->values();

    expect($served->all())->toBe([$first->id, $second->id]);
});

test('a page carries only what the panel shows', function () {
    Advert::factory()->sponsorship()->create(['alt_text' => 'Poster']);

    expect(array_keys(advertOnAPageView()))->toBe(['id', 'title', 'owner', 'desktop_image', 'mobile_image', 'alt_text']);
});

test('with no sponsorship live, a page carries none for the panel, whatever else is live', function () {
    Advert::factory()->sponsorship()->create(['active' => false]);
    Advert::factory()->create();

    expect(advertOnAPageView())->toBeNull();
});

test('an advert switched on in the Office is served at once, not ten minutes later', function () {
    $waiting = Advert::factory()->sponsorship()->create(['active' => false]);

    expect(advertOnAPageView())->toBeNull();

    makeAdvertsLive($waiting);

    expect(advertOnAPageView()['id'])->toBe($waiting->id);
});

test('an advert switched off in the Office stops being served at once', function () {
    $advert = Advert::factory()->sponsorship()->create();

    expect(advertOnAPageView()['id'])->toBe($advert->id);

    $advert->forceFill(['active' => false])->save();

    expect(advertOnAPageView())->toBeNull();
});

test('making an advert live leaves the other live adverts live', function () {
    $alreadyLive = Advert::factory()->create();
    $newlyLive = Advert::factory()->create(['active' => false]);

    makeAdvertsLive($newlyLive);

    expect($alreadyLive->fresh()->active)->toBeTrue()
        ->and($newlyLive->fresh()->active)->toBeTrue();
});

test('several adverts can be made live together', function () {
    $adverts = Advert::factory()->count(3)->create(['active' => false]);

    makeAdvertsLive(...$adverts);

    expect(Advert::query()->live()->count())->toBe(3);
});

test('page views take the live adverts in turn', function () {
    [$a, $b, $c] = Advert::factory()->count(3)->create()->all();

    $served = collect(range(1, 6))->map(fn () => advertForPageView(aVisitor()))->all();

    expect($served)->toBe([$a->id, $b->id, $c->id, $a->id, $b->id, $c->id]);
});

test('nobody is shown the same advert twice running, even when others look in between', function () {
    Advert::factory()->count(2)->create();
    $reader = aVisitor();
    $other = aVisitor();

    $readerSaw = [];
    $otherSaw = [];

    foreach (range(1, 10) as $round) {
        $readerSaw[] = advertForPageView($reader);
        $otherSaw[] = advertForPageView($other);
    }

    expect(sawTheSameAdvertTwiceRunning($readerSaw))->toBeFalse()
        ->and(sawTheSameAdvertTwiceRunning($otherSaw))->toBeFalse();
});

test('one visitor browsing on their own sees every advert in turn', function () {
    [$a, $b, $c] = Advert::factory()->count(3)->create()->all();
    $reader = aVisitor();

    $seen = collect(range(1, 6))->map(fn () => advertForPageView($reader))->all();

    expect($seen)->toBe([$a->id, $b->id, $c->id, $a->id, $b->id, $c->id]);
});

/*
 * Never showing anybody the same advert twice running can send a view past
 * the advert whose turn it was, which puts the advert taking it two views
 * ahead for a moment. The next view that is free to go anywhere goes to the
 * one behind, so they come back level.
 */
test('the shares stay level, a view or two apart, however visitors come and go', function () {
    $adverts = Advert::factory()->count(3)->create();
    $regulars = [aVisitor(), aVisitor(), aVisitor()];

    // Regulars browsing a few pages at a time, with passers-by in between.
    $visits = [0, 0, 1, null, 0, 2, 2, 1, null, 1, 0, 0, 0, 2, null, 1, 2, 0, null, null, 1, 1, 2, 0, 1, 2, null, 0, 0, 1];
    $served = array_map(fn (?int $regular) => advertForPageView($regular === null ? aVisitor() : $regulars[$regular]), $visits);

    $counts = array_count_values($served);

    expect(array_keys($counts))->toEqualCanonicalizing($adverts->modelKeys())
        ->and(max($counts) - min($counts))->toBeLessThanOrEqual(2);
});

test('an advert switched on joins at its share, without a run of catch-up views', function () {
    Advert::factory()->count(2)->create();

    foreach (range(1, 21) as $view) {
        advertForPageView(aVisitor());
    }

    makeAdvertsLive(Advert::factory()->create(['active' => false]));

    $counts = array_count_values(array_map(fn () => advertForPageView(aVisitor()), range(1, 9)));

    expect(array_values($counts))->toBe([3, 3, 3]);
});

test('with one advert live, every page view shows it, to the same visitor too', function () {
    $only = Advert::factory()->create();
    $reader = aVisitor();

    expect([advertForPageView($reader), advertForPageView($reader), advertForPageView($reader)])
        ->toBe([$only->id, $only->id, $only->id]);
});

test('the sponsorship a page shows is remembered in the visitor\'s session', function () {
    $advert = Advert::factory()->sponsorship()->create();

    $this->get(route('home'))->assertSessionHas(AdvertRotation::for(AdvertPool::Sponsorship)->sessionKey(), $advert->id);
});

/*
 * Sponsorships and in-list adverts take turns separately: each pool has its
 * own list, counts and memory of what the visitor saw last.
 */
test('each pool only ever gives out its own adverts', function () {
    $sponsorship = Advert::factory()->sponsorship()->create();
    $inList = Advert::factory()->create();
    $reader = aVisitor();

    $panel = collect(range(1, 4))->map(fn () => AdvertRotation::for(AdvertPool::Sponsorship)->next($reader)['id'])->unique()->all();
    $slots = collect(range(1, 4))->map(fn () => AdvertRotation::for(AdvertPool::InList)->next($reader)['id'])->unique()->all();

    expect($panel)->toBe([$sponsorship->id])
        ->and($slots)->toBe([$inList->id])
        ->and(collect(AdvertRotation::for(AdvertPool::InList)->live())->pluck('id')->all())->toBe([$inList->id])
        ->and(collect(AdvertRotation::for(AdvertPool::Sponsorship)->live())->pluck('id')->all())->toBe([$sponsorship->id]);
});

test('views in one pool do not move the other pool\'s turn on', function () {
    [$a, $b] = Advert::factory()->count(2)->create()->all();
    Advert::factory()->sponsorship()->count(2)->create();

    $inList = [];

    foreach (range(1, 4) as $view) {
        AdvertRotation::for(AdvertPool::Sponsorship)->next(aVisitor());
        AdvertRotation::for(AdvertPool::Sponsorship)->next(aVisitor());
        $inList[] = AdvertRotation::for(AdvertPool::InList)->next(aVisitor())['id'];
    }

    expect($inList)->toBe([$a->id, $b->id, $a->id, $b->id]);
});

test('the last advert seen is kept per pool, so a visitor can see one of each', function () {
    $sponsorship = Advert::factory()->sponsorship()->create();
    $inList = Advert::factory()->create();
    $reader = aVisitor();

    AdvertRotation::for(AdvertPool::Sponsorship)->next($reader);
    AdvertRotation::for(AdvertPool::InList)->next($reader);

    expect($reader->session()->get(AdvertRotation::for(AdvertPool::Sponsorship)->sessionKey()))->toBe($sponsorship->id)
        ->and($reader->session()->get(AdvertRotation::for(AdvertPool::InList)->sessionKey()))->toBe($inList->id);
});

test('saving an advert forgets both pools\' lists', function () {
    Advert::factory()->sponsorship()->create();
    Advert::factory()->create();
    AdvertRotation::for(AdvertPool::Sponsorship)->live();
    AdvertRotation::for(AdvertPool::InList)->live();

    Advert::factory()->create();

    expect(Cache::has(AdvertRotation::cacheKey(AdvertPool::Sponsorship)))->toBeFalse()
        ->and(Cache::has(AdvertRotation::cacheKey(AdvertPool::InList)))->toBeFalse();
});

test('moving an advert from the listings to sponsorship moves it from the slots to the panel at once', function () {
    $advert = Advert::factory()->create(['desktop_image' => 'sponsors/d.webp', 'mobile_image' => 'sponsors/m.webp']);

    expect(advertOnAPageView())->toBeNull();

    $advert->forceFill(['sponsorship' => true])->save();

    expect(advertOnAPageView()['id'])->toBe($advert->id)
        ->and(AdvertRotation::for(AdvertPool::InList)->live())->toBe([]);
});

test('the slots\' starting advert is only given on pages with slots', function () {
    $inList = Advert::factory()->create();

    $listings = $this->get('/listings')->assertOk()->viewData('page')['props'];
    $home = $this->get(route('home'))->assertOk()->viewData('page')['props'];

    expect($listings['listingAdvert']['id'])->toBe($inList->id)
        ->and($listings['liveAdverts'][0]['id'])->toBe($inList->id)
        ->and($home['listingAdvert'])->toBeNull();
});
