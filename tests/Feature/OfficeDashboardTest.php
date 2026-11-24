<?php

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Models\Category;
use App\Models\Feature;
use App\Models\FeatureRead;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Nova\Cards\AdvertReach;
use App\Nova\Cards\FeaturesByCategory;
use App\Nova\Cards\HeadlineCounts;
use App\Nova\Cards\ListingsExport;
use App\Nova\Cards\MostReadFeatures;
use App\Nova\Cards\PromoterLeaders;
use App\Nova\Cards\ReadingByCategory;
use App\Nova\Cards\SiteVisits;
use App\Nova\Cards\VenueLeaders;
use App\Nova\Cards\WriterLeaders;
use App\Nova\Dashboards\Main;
use App\Nova\Feature as FeatureResource;
use App\Nova\Promoter as PromoterResource;
use App\Nova\Venue as VenueResource;
use Laravel\Nova\Http\Requests\NovaRequest;

function novaRequest(): NovaRequest
{
    return NovaRequest::create('/nova-api/dashboards/main');
}

test('the dashboard is named for the office rather than Nova', function () {
    expect((new Main)->name())->toBe('Office')
        ->and((new Main)->label())->toBe('Office');
});

/*
 * The shop's three orders cards are switched off while the shop is quiet,
 * so the dashboard is the five office cards alone until they come back.
 */
test('the dashboard shows the five office cards, the shop\'s orders cards switched off', function () {
    expect(collect((new Main)->cards())->map(fn ($card) => $card::class)->all())
        ->toBe([
            SiteVisits::class,
            FeaturesByCategory::class,
            AdvertReach::class,
            ListingsExport::class,
            HeadlineCounts::class,
        ]);
});

test('each list sits over its own resource\'s index', function (string $resource, string $model, array $cards) {
    $resourceCards = (new $resource(new $model))->cards(novaRequest());

    expect(collect($resourceCards)->map(fn ($card) => $card::class)->all())->toBe($cards);
})->with([
    'features' => [FeatureResource::class, Feature::class, [ReadingByCategory::class, MostReadFeatures::class, WriterLeaders::class]],
    'promoters' => [PromoterResource::class, Promoter::class, [PromoterLeaders::class]],
    'venues' => [VenueResource::class, Venue::class, [VenueLeaders::class]],
]);

/**
 * One section of the headline counts card, found by its heading, as
 * label => value.
 *
 * @return array<string, int>
 */
function headlineCounts(string $heading): array
{
    $section = collect((new HeadlineCounts)->jsonSerialize()['sections'])->firstWhere('heading', $heading);

    return array_column($section['counts'], 'value', 'label');
}

test('the statistics card has venues, then events, then writers', function () {
    $card = (new HeadlineCounts)->jsonSerialize();

    expect($card['heading'])->toBe('Statistics');
    expect(collect($card['sections'])->mapWithKeys(fn (array $section): array => [
        $section['heading'] => array_column($section['counts'], 'label'),
    ])->all())->toBe([
        'Venues' => ['Venues', 'Upcoming events'],
        'Events' => ['Promoters', 'Events listed', 'Bookmarks'],
        'Writers' => ['Writers', 'Articles'],
    ]);
});

test('reading time is grouped by category name, longest first', function () {
    $interviews = Tag::factory()->create(['tag' => 'Interviews']);
    $archives = Tag::factory()->create(['tag' => 'Archives']);

    FeatureRead::factory()->create([
        'feature_id' => Feature::factory()->create(['tag_id' => $interviews->id])->id,
        'seconds_read' => 600,
    ]);
    $archive = Feature::factory()->create(['tag_id' => $archives->id]);
    FeatureRead::factory()->create(['feature_id' => $archive->id, 'seconds_read' => 3600]);
    FeatureRead::factory()->create(['feature_id' => $archive->id, 'seconds_read' => 3600, 'read_on' => today()->subDay()]);

    $card = (new FeaturesByCategory)->jsonSerialize();

    expect($card['total'])->toBe(7800)
        ->and(collect($card['slices'])->mapWithKeys(fn (array $slice): array => [$slice['label'] => $slice['value']])->all())
        ->toBe(['Archives' => 7200, 'Interviews' => 600])
        ->and($card['linkText'])->toBe('View all Features');
});

test('reading before the last 30 days is left out', function () {
    FeatureRead::factory()->create(['read_on' => today()->subDays(FeaturesByCategory::DAYS), 'seconds_read' => 900]);
    FeatureRead::factory()->create(['read_on' => today()->subDays(FeaturesByCategory::DAYS - 1), 'seconds_read' => 60]);

    expect((new FeaturesByCategory)->jsonSerialize()['total'])->toBe(60);
});

test('a feature read only before the last 30 days is not among the most read', function () {
    $lastMonth = Feature::factory()->create(['title' => 'Read last month']);
    FeatureRead::factory()->create(['feature_id' => $lastMonth->id, 'read_on' => today()->subDays(MostReadFeatures::DAYS)]);
    $thisMonth = Feature::factory()->create(['title' => 'Read this month']);
    FeatureRead::factory()->create(['feature_id' => $thisMonth->id, 'read_on' => today()->subDays(MostReadFeatures::DAYS - 1)]);

    $leaders = (new MostReadFeatures)->jsonSerialize()['leaders'];

    expect(array_column($leaders, 'name'))->toBe(['Read this month']);
});

test('reading in features with no category is labelled rather than dropped', function () {
    FeatureRead::factory()->create(['feature_id' => Feature::factory()->untagged()->create()->id]);

    $slices = (new FeaturesByCategory)->jsonSerialize()['slices'];

    expect($slices)->toHaveCount(1)
        ->and($slices[0]['label'])->toBe('Uncategorised');
});

test('every slice gets a colour, even past the end of the palette', function () {
    Tag::factory()->count(8)->create()->each(fn ($tag) => FeatureRead::factory()->create([
        'feature_id' => Feature::factory()->create(['tag_id' => $tag->id])->id,
    ]));

    $colours = collect((new FeaturesByCategory)->jsonSerialize()['slices'])->pluck('colour');

    expect($colours)->toHaveCount(8)
        ->and($colours->filter())->toHaveCount(8);
});

test('the most read are the features the most people read, the longer read first among equals', function () {
    $mostRead = Feature::factory()->create(['title' => 'Most read']);
    FeatureRead::factory()->create(['feature_id' => $mostRead->id, 'read_count' => 3, 'seconds_read' => 600, 'reached_end_count' => 2]);
    FeatureRead::factory()->create(['feature_id' => $mostRead->id, 'read_count' => 1, 'seconds_read' => 200, 'reached_end_count' => 0, 'read_on' => today()->subDay()]);

    $lingered = Feature::factory()->create(['title' => 'Lingered over']);
    FeatureRead::factory()->create(['feature_id' => $lingered->id, 'read_count' => 1, 'seconds_read' => 5000]);

    Feature::factory()->count(5)->create()->each(fn (Feature $feature, int $index) => FeatureRead::factory()->create([
        'feature_id' => $feature->id,
        'seconds_read' => 100 + $index,
    ]));

    Feature::factory()->create(['title' => 'Never read']);

    $leaders = (new FeaturesByCategory)->jsonSerialize()['leaders'];

    expect($leaders)->toHaveCount(FeaturesByCategory::LEADERS)
        ->and(collect($leaders)->pluck('name'))->not->toContain('Never read')
        ->and($leaders[0])->toBe([
            'name' => 'Most read',
            'seconds' => 800,
            'reads' => 4,
            'averageRead' => 200,
            'reachedEnd' => 50,
            'link' => '/resources/features/'.$mostRead->id,
        ])
        ->and($leaders[1]['name'])->toBe('Lingered over')
        ->and(collect($leaders)->slice(2)->pluck('seconds')->all())->toBe([104, 103, 102]);
});

test('with nothing read, the card says so', function () {
    Feature::factory()->create();

    $card = (new FeaturesByCategory)->jsonSerialize();

    expect($card['total'])->toBe(0)
        ->and($card['slices'])->toBe([])
        ->and($card['leaders'])->toBe([])
        ->and($card['emptyText'])->toBe('No feature has been read in the last 30 days.');
});

test('the features index has the reading card split in two, the time by category in one and the most read in the other', function () {
    $feature = Feature::factory()->create([
        'title' => 'From the archive',
        'tag_id' => Tag::factory()->create(['tag' => 'Archives'])->id,
    ]);
    FeatureRead::factory()->create(['feature_id' => $feature->id, 'seconds_read' => 600]);

    $byCategory = (new ReadingByCategory)->jsonSerialize();
    $mostRead = (new MostReadFeatures)->jsonSerialize();

    expect($byCategory['total'])->toBe(600)
        ->and(array_column($byCategory['slices'], 'value', 'label'))->toBe(['Archives' => 600])
        ->and($byCategory)->not->toHaveKey('leaders');
    expect(array_column($mostRead['leaders'], 'name'))->toBe(['From the archive'])
        ->and($mostRead)->not->toHaveKey('slices');
});

test('with nothing read, both halves on the features index say so', function () {
    Feature::factory()->create();

    expect((new ReadingByCategory)->jsonSerialize())->toMatchArray([
        'total' => 0,
        'emptyText' => 'No feature has been read in the last 30 days.',
    ]);
    expect((new MostReadFeatures)->jsonSerialize())->toMatchArray([
        'leaders' => [],
        'emptyText' => 'No feature has been read in the last 30 days.',
    ]);
});

/**
 * Staff hold every role without being marked as writing, so an account
 * that is not marked is left out however much it could write.
 */
test('the writers counts are everyone marked as writing, published or not, and every article', function () {
    $prolific = User::factory()->writer()->create(['name' => 'Ada Prolific']);
    $pair = collect(['Bo Occasional', 'Cy Occasional'])
        ->map(fn (string $name) => User::factory()->writer()->create(['name' => $name]));
    User::factory()->writer()->create(['name' => 'Di Unpublished']);
    User::factory()->create(['name' => 'Ed Office']);

    Feature::factory()->count(3)->create(['user_id' => $prolific->id]);
    $pair->each(fn (User $writer) => Feature::factory()->create(['user_id' => $writer->id]));

    expect(headlineCounts('Writers'))->toBe(['Writers' => 4, 'Articles' => 5]);
});

test('the writers card ranks contributors by what they have published', function () {
    $writers = collect(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'])
        ->map(fn (string $name) => User::factory()->writer()->create(['name' => $name]));

    foreach ([6, 5, 4, 3, 2, 1] as $index => $articles) {
        Feature::factory()->count($articles)->create(['user_id' => $writers[$index]->id]);
    }

    $rows = (new WriterLeaders)->jsonSerialize()['rows'];

    expect(collect($rows)->pluck('name')->all())
        ->toBe(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth'])
        ->and($rows[0]['count'])->toBe(6);
});

/**
 * An article does not stop counting once its date has passed, the way an
 * event does - so a writer's standing is everything they have written.
 */
test('writers with nothing published are left out of the list', function () {
    User::factory()->writer()->count(4)->create();

    expect((new WriterLeaders)->jsonSerialize()['rows'])->toBe([]);
});

test('the venues counts are every venue and every event still to come', function () {
    // The listing factory makes a venue of its own, so the events are hung
    // off one of the three rather than quietly adding five more.
    $venues = Venue::factory()->count(3)->create();
    Listing::factory()->count(5)->create([
        'venue_id' => $venues->first()->id,
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->create([
        'venue_id' => $venues->first()->id,
        'start_date' => today()->subMonth(),
        'end_date' => today()->subWeek(),
    ]);

    expect(headlineCounts('Venues'))->toBe(['Venues' => 3, 'Upcoming events' => 5]);
});

test('the venues card lists the five with the most coming up', function () {
    $venues = collect(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'])
        ->map(fn (string $name) => Venue::factory()->create(['name' => $name]));

    foreach ([6, 5, 4, 3, 2, 1] as $index => $events) {
        Listing::factory()->count($events)->create([
            'venue_id' => $venues[$index]->id,
            'start_date' => today()->addWeek(),
        ]);
    }

    expect(collect((new VenueLeaders)->jsonSerialize()['rows'])->pluck('name')->all())
        ->toBe(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth']);
});

test('the export card offers next month as a signed download', function () {
    $this->travelTo('2026-09-13');

    $card = (new ListingsExport)->jsonSerialize();

    expect($card['heading'])->toBe('Listings')
        ->and($card['exportHeading'])->toBe('Download listings for the October issue')
        ->and($card['buttonText'])->toBe('Download October listings')
        ->and($card['filename'])->toBe('OCT26_Listings.csv')
        ->and($card['linkText'])->toBe('View all current listings')
        ->and($card['url'])->toContain('signature=');

    $this->get($card['url'])->assertOk();
});

test('the export card counts what the download will contain', function () {
    $this->travelTo('2026-09-13');

    // Two inside next month, one spanning into it, one finishing before it.
    Listing::factory()->create(['start_date' => '2026-10-04']);
    Listing::factory()->create(['start_date' => '2026-10-20']);
    Listing::factory()->create(['start_date' => '2026-08-01', 'end_date' => '2026-10-09']);
    Listing::factory()->create(['start_date' => '2026-09-02', 'end_date' => '2026-09-30']);

    expect((new ListingsExport)->jsonSerialize()['count'])->toBe(3);
});

/**
 * One of the listings card's charts, found by its heading, as
 * label => listings.
 *
 * @return array<string, int>
 */
function listingsChart(string $heading): array
{
    $chart = collect((new ListingsExport)->jsonSerialize()['charts'])->firstWhere('heading', $heading);

    return array_column($chart['slices'], 'value', 'label');
}

test('the listings card counts what is coming up by category, the most first', function () {
    $music = Category::factory()->create(['name' => 'Music']);
    $film = Category::factory()->create(['name' => 'Film']);
    Listing::factory()->count(3)->create(['category_id' => $music->id, 'start_date' => today()->addWeek()]);
    Listing::factory()->count(2)->create(['category_id' => $film->id, 'start_date' => today()->addWeek()]);
    Listing::factory()->create(['category_id' => $film->id, 'start_date' => today()->subMonth(), 'end_date' => today()->subWeek()]);
    Listing::factory()->create(['category_id' => null, 'start_date' => today()->addWeek()]);

    expect(listingsChart('Upcoming by category'))->toBe(['Music' => 3, 'Film' => 2, 'Uncategorised' => 1]);
});

/**
 * September to January are named, in order, and February on is later.
 * November and December have nothing on, so they have no slice to draw.
 */
test('the listings card counts what is coming up by month, a listing already running in this one', function () {
    $this->travelTo('2026-09-13');

    Listing::factory()->create(['start_date' => '2026-08-01', 'end_date' => '2026-09-30']);
    Listing::factory()->create(['start_date' => '2026-09-20']);
    Listing::factory()->count(2)->create(['start_date' => '2026-10-04']);
    Listing::factory()->create(['start_date' => '2027-01-10']);
    Listing::factory()->count(2)->create(['start_date' => '2027-02-01']);
    Listing::factory()->create(['start_date' => '2027-06-15']);
    Listing::factory()->create(['start_date' => '2026-09-02', 'end_date' => '2026-09-10']);

    expect(listingsChart('Upcoming by month'))->toBe(['September' => 2, 'October' => 2, 'January' => 1, 'Later' => 3]);
});

test('each chart on the listings card colours its slices from the start of the dashboard palette', function () {
    Listing::factory()->create(['start_date' => today()->addWeek()]);

    $charts = (new ListingsExport)->jsonSerialize()['charts'];

    expect(array_map(fn (array $chart): array => array_column($chart['slices'], 'colour'), $charts))
        ->toBe([['#F5573B'], ['#F5573B']]);
});

test('with nothing coming up, the listings card has nothing to chart and says so', function () {
    Listing::factory()->create(['start_date' => today()->subMonth(), 'end_date' => today()->subWeek()]);

    $card = (new ListingsExport)->jsonSerialize();

    expect(array_column($card['charts'], 'slices'))->toBe([[], []])
        ->and($card['emptyText'])->toBe('No listings are coming up.');
});

test('the promoters card ranks the busiest five by upcoming events', function () {
    $promoters = collect(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'])
        ->mapWithKeys(fn (string $name): array => [$name => Promoter::factory()->create(['name' => $name])]);

    foreach ([6, 5, 4, 3, 2, 1] as $index => $events) {
        Listing::factory()->count($events)->create([
            'promoter_id' => $promoters->values()[$index]->id,
            'start_date' => today()->addWeek(),
        ]);
    }

    $card = (new PromoterLeaders)->jsonSerialize();

    expect($card['heading'])->toBe('Top 5 Promoters')
        ->and(collect($card['rows'])->pluck('name')->all())
        ->toBe(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth'])
        ->and($card['rows'][0]['count'])->toBe(6)
        ->and($card)->not->toHaveKey('listHeading')
        ->and($card)->not->toHaveKey('linkText');

    $counts = headlineCounts('Events');

    expect($counts['Promoters'])->toBe(6)
        ->and($counts['Events listed'])->toBe(21);
});

test('finished events do not count towards a promoter\'s standing', function () {
    $past = Promoter::factory()->create(['name' => 'Had A Good Year']);
    $present = Promoter::factory()->create(['name' => 'On Now']);

    Listing::factory()->count(5)->create([
        'promoter_id' => $past->id,
        'start_date' => today()->subMonths(2),
        'end_date' => today()->subMonth(),
    ]);
    Listing::factory()->count(2)->create([
        'promoter_id' => $present->id,
        'start_date' => today()->addWeek(),
    ]);

    $promoters = (new PromoterLeaders)->jsonSerialize()['rows'];

    expect(collect($promoters)->pluck('name')->all())->toBe(['On Now'])
        ->and($promoters[0]['count'])->toBe(2);
});

test('an event running today still counts', function () {
    $promoter = Promoter::factory()->create(['name' => 'Mid Run']);

    Listing::factory()->create([
        'promoter_id' => $promoter->id,
        'start_date' => today()->subWeek(),
        'end_date' => today(),
    ]);

    expect((new PromoterLeaders)->jsonSerialize()['rows'][0]['count'])->toBe(1);
});

test('promoters with nothing on are left out of the list', function () {
    Promoter::factory()->count(3)->create();

    expect((new PromoterLeaders)->jsonSerialize()['rows'])->toBe([]);
});

test('the promoter count is everyone on the books, busy or not', function () {
    $active = Promoter::factory()->create(['name' => 'On Now']);
    Promoter::factory()->count(4)->create();

    Listing::factory()->create([
        'promoter_id' => $active->id,
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->create([
        'promoter_id' => Promoter::factory()->create()->id,
        'start_date' => today()->subMonth(),
        'end_date' => today()->subWeek(),
    ]);

    $counts = headlineCounts('Events');

    // Six promoters exist; only one has anything coming up, and both the
    // finished event and the upcoming one are events that were promoted.
    expect($counts['Promoters'])->toBe(6)
        ->and($counts['Events listed'])->toBe(2);
});

test('the all-time count is everything listed, promoted or not', function () {
    Listing::factory()->create([
        'promoter_id' => Promoter::factory()->create()->id,
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->count(3)->create([
        'promoter_id' => null,
        'start_date' => today()->addWeek(),
    ]);
    Listing::factory()->create([
        'promoter_id' => null,
        'start_date' => today()->subMonths(2),
        'end_date' => today()->subMonth(),
    ]);

    expect(headlineCounts('Events')['Events listed'])->toBe(5);
});

test('each leaderboard says what it is counting', function () {
    expect((new VenueLeaders)->jsonSerialize()['unit'])->toBe('event')
        ->and((new PromoterLeaders)->jsonSerialize()['unit'])->toBe('event')
        ->and((new WriterLeaders)->jsonSerialize()['unit'])->toBe('article');
});

/**
 * One section of the adverts card, found by the slot it names.
 *
 * @return array<string, mixed>
 */
function advertSection(string $heading): array
{
    return collect((new AdvertReach)->jsonSerialize()['sections'])->firstWhere('heading', $heading);
}

test('the adverts card has the in-list slots, then the sponsorship panel', function () {
    $card = (new AdvertReach)->jsonSerialize();

    expect(array_column($card['sections'], 'heading'))->toBe(['In-list', 'Sponsorship panel'])
        ->and($card['linkText'])->toBe('View all adverts');
});

test('each adverts section totals the last thirty days of its own slot, archived adverts included', function () {
    $live = Advert::factory()->create();
    $archived = Advert::factory()->create();
    $sponsorship = Advert::factory()->sponsorship()->create();
    AdvertEvent::factory()->count(6)->for($live)->create(['type' => 'impression']);
    AdvertEvent::factory()->count(2)->for($live)->create(['type' => 'click']);
    AdvertEvent::factory()->count(2)->for($archived)->create(['type' => 'impression']);
    AdvertEvent::factory()->count(3)->for($sponsorship)->create(['type' => 'impression']);
    AdvertEvent::factory()->for($sponsorship)->create(['type' => 'impression', 'created_at' => now()->subDays(45)]);
    $archived->delete();

    $inList = advertSection('In-list');
    $panel = advertSection('Sponsorship panel');

    expect($inList['impressions'])->toBe(8)
        ->and($inList['summary'])->toBe('Last 30 days · 2 clicks · 25% click-through');
    expect($panel['impressions'])->toBe(3)
        ->and($panel['summary'])->toBe('Last 30 days · 0 clicks · 0% click-through');
});

test('the adverts sections survive a period with no impressions', function () {
    $sections = (new AdvertReach)->jsonSerialize()['sections'];

    expect(array_column($sections, 'impressions'))->toBe([0, 0])
        ->and(array_column($sections, 'summary'))->each->toContain('0% click-through');
});

test('a running sponsorship is shown in the sponsorship panel section and not among the in-list adverts', function () {
    Advert::factory()->sponsorship()->create(['title' => 'Stroud Arts Festival']);

    $advert = advertSection('Sponsorship panel')['adverts'][0];

    expect($advert['title'])->toBe('Stroud Arts Festival')
        ->and($advert['image'])->toBe(config('filesystems.disks.do.url').'/sponsors/desktop.webp');
    expect(advertSection('In-list')['adverts'])->toBe([]);
});

test('the sponsorship panel section shows only the newest sponsorship and says when another is taking turns with it', function () {
    Advert::factory()->sponsorship()->create(['title' => 'Good on Paper', 'run_from' => today()->subMonth()]);
    Advert::factory()->sponsorship()->create(['title' => 'Stroud Arts Festival', 'run_from' => today()->subWeek()]);

    $panel = advertSection('Sponsorship panel');

    expect(array_column($panel['adverts'], 'title'))->toBe(['Stroud Arts Festival'])
        ->and($panel['notice'])->toBe('2 sponsorships are live and taking turns in the panel.');
});

test('a sponsorship on its own in the panel raises no notice', function () {
    Advert::factory()->sponsorship()->create();

    expect(advertSection('Sponsorship panel')['notice'])->toBeNull();
});

test('an advert whose run has ended is not shown as current', function () {
    Advert::factory()->create([
        'active' => true,
        'run_from' => today()->subMonths(2),
        'run_to' => today()->subMonth(),
    ]);

    expect(array_column((new AdvertReach)->jsonSerialize()['sections'], 'adverts'))->toBe([[], []]);
});

test('the in-list section has a slide for every live in-list advert, newest first, each with its own figures', function () {
    $older = Advert::factory()->create(['title' => 'Stroud Arts Festival', 'run_from' => today()->subMonth()]);
    $newer = Advert::factory()->create(['title' => 'Los Fastidios', 'run_from' => today()->subWeek()]);
    Advert::factory()->create(['title' => 'Switched off', 'active' => false]);
    Advert::factory()->sponsorship()->create(['title' => 'Prince Albert']);

    AdvertEvent::factory()->count(6)->create(['advert_id' => $older->id, 'type' => 'impression']);
    AdvertEvent::factory()->count(3)->create(['advert_id' => $older->id, 'type' => 'click']);
    AdvertEvent::factory()->count(4)->create(['advert_id' => $newer->id, 'type' => 'impression']);
    AdvertEvent::factory()->create(['advert_id' => $newer->id, 'type' => 'click']);
    AdvertEvent::factory()->create(['advert_id' => $newer->id, 'type' => 'impression', 'created_at' => now()->subDays(45)]);

    $slides = advertSection('In-list')['adverts'];

    expect(array_column($slides, 'title'))->toBe(['Los Fastidios', 'Stroud Arts Festival'])
        ->and($slides[0]['impressions'])->toBe(4)
        ->and($slides[0]['summary'])->toContain('· 1 click ·')
        ->and($slides[1]['impressions'])->toBe(6)
        ->and($slides[1]['summary'])->toContain('3 clicks')
        ->and($slides[1]['summary'])->toContain('50%')
        ->and($slides[1]['link'])->toBe('/resources/adverts/'.$older->id);
});

/**
 * Every save counts, not every listing saved: one listing three people
 * kept is three readings of it, and this figure is here to show how much
 * of that is going on.
 */
test('the events counts take in every bookmark anybody has made', function () {
    $readers = User::factory()->count(3)->create();
    $saved = Listing::factory()->count(2)->create();
    Listing::factory()->create();

    $saved->each(fn (Listing $listing) => $listing->bookmarkedBy()->attach($readers));

    $counts = headlineCounts('Events');

    expect($counts['Bookmarks'])->toBe(6)
        ->and($counts['Events listed'])->toBe(3);
});

test('the events counts show no bookmarks when nobody has saved one', function () {
    Listing::factory()->count(4)->create();

    expect(headlineCounts('Events')['Bookmarks'])->toBe(0);
});
