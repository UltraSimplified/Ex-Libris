<?php

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Models\Feature;
use App\Models\FeatureRead;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Nova\Cards\AdvertReach;
use App\Nova\Cards\FeaturesByCategory;
use App\Nova\Cards\ListingsExport;
use App\Nova\Cards\PromoterLeaders;
use App\Nova\Cards\SiteVisits;
use App\Nova\Cards\VenueLeaders;
use App\Nova\Cards\WriterLeaders;
use App\Nova\Dashboards\Main;
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
 * so the dashboard is the seven office cards alone until they come back.
 */
test('the dashboard shows the seven office cards, the shop\'s orders cards switched off', function () {
    expect(collect((new Main)->cards())->map(fn ($card) => $card::class)->all())
        ->toBe([
            SiteVisits::class,
            FeaturesByCategory::class,
            AdvertReach::class,
            ListingsExport::class,
            WriterLeaders::class,
            PromoterLeaders::class,
            VenueLeaders::class,
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

test('the most read are the features read longest, with their readers and how many finished', function () {
    $longest = Feature::factory()->create(['title' => 'Longest']);
    FeatureRead::factory()->create(['feature_id' => $longest->id, 'read_count' => 3, 'seconds_read' => 600, 'reached_end_count' => 2]);
    FeatureRead::factory()->create(['feature_id' => $longest->id, 'read_count' => 1, 'seconds_read' => 200, 'reached_end_count' => 0, 'read_on' => today()->subDay()]);

    Feature::factory()->count(5)->create()->each(fn (Feature $feature, int $index) => FeatureRead::factory()->create([
        'feature_id' => $feature->id,
        'seconds_read' => 100 + $index,
    ]));

    Feature::factory()->create(['title' => 'Never read']);

    $leaders = (new FeaturesByCategory)->jsonSerialize()['leaders'];

    expect($leaders)->toHaveCount(FeaturesByCategory::LEADERS)
        ->and(collect($leaders)->pluck('name'))->not->toContain('Never read')
        ->and($leaders[0])->toBe([
            'name' => 'Longest',
            'seconds' => 800,
            'reads' => 4,
            'averageRead' => 200,
            'reachedEnd' => 50,
            'link' => '/resources/features/'.$longest->id,
        ])
        ->and($leaders[1]['seconds'])->toBe(104);
});

test('with nothing read, the card says so', function () {
    Feature::factory()->create();

    $card = (new FeaturesByCategory)->jsonSerialize();

    expect($card['total'])->toBe(0)
        ->and($card['slices'])->toBe([])
        ->and($card['leaders'])->toBe([])
        ->and($card['emptyText'])->toBe('No feature has been read in the last 30 days.');
});

test('the writers card counts the roster and the body of work', function () {
    $prolific = User::factory()->writer()->create(['name' => 'Ada Prolific']);
    $pair = collect(['Bo Occasional', 'Cy Occasional'])
        ->map(fn (string $name) => User::factory()->writer()->create(['name' => $name]));
    User::factory()->writer()->create(['name' => 'Di Unpublished']);

    Feature::factory()->count(3)->create(['user_id' => $prolific->id]);
    $pair->each(fn (User $writer) => Feature::factory()->create(['user_id' => $writer->id]));

    $card = (new WriterLeaders)->jsonSerialize();
    $counts = collect($card['counts'])->keyBy('label');

    expect($card['heading'])->toBe('Writers')
        ->and($card['listHeading'])->toBe('Top 5 Contributors')
        ->and($counts['Writers']['value'])->toBe(4)
        ->and($counts['Articles']['value'])->toBe(5)
        ->and($card['linkText'])->toBe('View all accounts');
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
test('writers with nothing published are left out of the list but still counted', function () {
    User::factory()->writer()->count(4)->create();

    $card = (new WriterLeaders)->jsonSerialize();

    expect($card['rows'])->toBe([])
        ->and(collect($card['counts'])->keyBy('label')['Writers']['value'])->toBe(4);
});

test('the venues card counts venues and upcoming events', function () {
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

    $card = (new VenueLeaders)->jsonSerialize();
    $counts = collect($card['counts'])->keyBy('label');

    expect($card['heading'])->toBe('Venues')
        ->and($card['listHeading'])->toBe('Top 5 Venues')
        ->and($counts['Venues']['value'])->toBe(3)
        ->and($counts['Upcoming events']['value'])->toBe(5)
        ->and($card['linkText'])->toBe('View all venues');
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

    expect($card['heading'])->toBe("October's Listings")
        ->and($card['buttonText'])->toBe('Download October Listings')
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

    $counts = collect($card['counts'])->keyBy('label');

    expect($card['heading'])->toBe('Promoters')
        ->and($card['listHeading'])->toBe('Top 5 Promoters')
        ->and($counts['Promoters']['value'])->toBe(6)
        ->and($counts['Events listed']['value'])->toBe(21)
        ->and(collect($card['rows'])->pluck('name')->all())
        ->toBe(['Busiest', 'Second', 'Third', 'Fourth', 'Fifth'])
        ->and($card['rows'][0]['count'])->toBe(6)
        ->and($card['linkText'])->toBe('View all promoters');
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

test('promoters with nothing on are left out of the list but still counted', function () {
    Promoter::factory()->count(3)->create();

    $card = (new PromoterLeaders)->jsonSerialize();

    expect($card['rows'])->toBe([])
        ->and(collect($card['counts'])->keyBy('label')['Promoters']['value'])->toBe(3);
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

    $counts = collect((new PromoterLeaders)->jsonSerialize()['counts'])->keyBy('label');

    // Six promoters exist; only one has anything coming up, and both the
    // finished event and the upcoming one are events that were promoted.
    expect($counts['Promoters']['value'])->toBe(6)
        ->and($counts['Events listed']['value'])->toBe(2);
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

    $counts = collect((new PromoterLeaders)->jsonSerialize()['counts'])->keyBy('label');

    expect($counts['Events listed']['value'])->toBe(5);
});

test('each leaderboard says what it is counting', function () {
    expect((new VenueLeaders)->jsonSerialize()['unit'])->toBe('event')
        ->and((new PromoterLeaders)->jsonSerialize()['unit'])->toBe('event')
        ->and((new WriterLeaders)->jsonSerialize()['unit'])->toBe('article');
});

test('the adverts card reports impressions, clicks and the rate', function () {
    AdvertEvent::factory()->count(8)->create(['type' => 'impression']);
    AdvertEvent::factory()->count(2)->create(['type' => 'click']);

    $card = (new AdvertReach)->jsonSerialize();

    expect($card['impressions'])->toBe(8)
        ->and($card['summary'])->toContain('2 clicks')
        ->and($card['summary'])->toContain('25%')
        ->and($card['linkText'])->toBe('View all adverts');
});

test('the adverts card survives a period with no impressions', function () {
    $card = (new AdvertReach)->jsonSerialize();

    expect($card['impressions'])->toBe(0)
        ->and($card['summary'])->toContain('0% click-through');
});

test('the adverts card shows the creative that is running', function () {
    Advert::factory()->create([
        'title' => 'Stroud Arts Festival',
        'sponsorship' => true,
        'desktop_image' => 'adverts/poster.png',
        'active' => true,
        'run_from' => today()->subWeek(),
        'run_to' => today()->addWeek(),
    ]);

    $advert = (new AdvertReach)->jsonSerialize()['adverts'][0];

    expect($advert['title'])->toBe('Stroud Arts Festival')
        ->and($advert['slot'])->toBe('Sponsorship panel')
        ->and($advert['image'])->toBe(config('filesystems.disks.do.url').'/adverts/poster.png');
});

test('an advert whose run has ended is not shown as current', function () {
    Advert::factory()->create([
        'active' => true,
        'run_from' => today()->subMonths(2),
        'run_to' => today()->subMonth(),
    ]);

    expect((new AdvertReach)->jsonSerialize()['adverts'])->toBe([]);
});

test('the adverts card has a slide for every live advert, newest first, each with its own figures', function () {
    $older = Advert::factory()->create(['title' => 'Stroud Arts Festival', 'run_from' => today()->subMonth()]);
    $newer = Advert::factory()->create(['title' => 'Los Fastidios', 'run_from' => today()->subWeek()]);
    Advert::factory()->create(['title' => 'Switched off', 'active' => false]);

    AdvertEvent::factory()->count(6)->create(['advert_id' => $older->id, 'type' => 'impression']);
    AdvertEvent::factory()->count(3)->create(['advert_id' => $older->id, 'type' => 'click']);
    AdvertEvent::factory()->count(4)->create(['advert_id' => $newer->id, 'type' => 'impression']);
    AdvertEvent::factory()->create(['advert_id' => $newer->id, 'type' => 'click']);
    AdvertEvent::factory()->create(['advert_id' => $newer->id, 'type' => 'impression', 'created_at' => now()->subDays(45)]);

    $slides = (new AdvertReach)->jsonSerialize()['adverts'];

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
test('the promoters card counts every bookmark anybody has made', function () {
    $readers = User::factory()->count(3)->create();
    $saved = Listing::factory()->count(2)->create();
    Listing::factory()->create();

    $saved->each(fn (Listing $listing) => $listing->bookmarkedBy()->attach($readers));

    $counts = collect((new PromoterLeaders)->jsonSerialize()['counts'])->keyBy('label');

    expect($counts['Bookmarks']['value'])->toBe(6)
        ->and($counts['Events listed']['value'])->toBe(3);
});

test('the promoters card shows no bookmarks when nobody has saved one', function () {
    Listing::factory()->count(4)->create();

    $counts = collect((new PromoterLeaders)->jsonSerialize()['counts'])->keyBy('label');

    expect($counts['Bookmarks']['value'])->toBe(0);
});
