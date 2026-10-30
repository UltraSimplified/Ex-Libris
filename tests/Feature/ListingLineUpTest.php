<?php

use App\Models\Listing;
use App\Services\Listings\ListingLineUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A line-up in the shape Nova's Flexible field writes, as the Office saves
 * it: nothing has cleaned it yet.
 *
 * @param  array<int, array<string, mixed>>  $artists
 */
function officeLineUp(array $artists): string
{
    return json_encode(array_map(fn (array $artist, int $index): array => [
        'layout' => 'artist',
        'key' => 'k'.$index,
        'attributes' => $artist,
    ], $artists, array_keys($artists)));
}

/**
 * @return array<int, array{name: string, playlist: ?string, video: ?string}>
 */
function lineUpOf(Listing $listing): array
{
    return app(ListingLineUp::class)->artists($listing->fresh()->line_up);
}

const BANDCAMP = '<iframe style="border: 0; width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=1/" seamless><a href="https://example.bandcamp.com">x</a></iframe>';

/*
 * The Office stores what it is given, and the listing page draws embeds as
 * markup, so the listing cleans its line-up whoever writes it.
 */
test('a line-up saved from the Office is cleaned as the listing saves', function () {
    $listing = Listing::factory()->create(['line_up' => officeLineUp([
        ['name' => 'Sun Ra Arkestra', 'playlist' => BANDCAMP, 'video' => '<script>alert(1)</script><iframe src="https://www.youtube.com/embed/abc123" onload="steal()"></iframe>'],
    ])]);

    [$artist] = lineUpOf($listing);

    expect($artist['playlist'])->toContain('width: 100%')->not->toContain('example.bandcamp.com')
        ->and($artist['video'])->toContain('youtube-nocookie.com/embed/abc123')
        ->and($artist['video'])->not->toContain('script')
        ->and($artist['video'])->not->toContain('onload');
});

/*
 * Saved from the Office, the line-up reaches the listing as the Collection
 * of groups Nova's Flexible field sets (Whitecube's Resolver::set), not as
 * JSON. Read only as JSON, it looked empty, and a line-up edited in the
 * Office was saved as none.
 */
test('a line-up set as the Office field sets it is kept when the listing saves', function () {
    $listing = Listing::factory()->create(['line_up' => null]);

    $listing->line_up = collect([
        ['layout' => 'artist', 'key' => 'a1', 'attributes' => ['name' => 'Jackson Almond', 'playlist' => BANDCAMP, 'video' => null]],
        ['layout' => 'artist', 'key' => 'b2', 'attributes' => ['name' => 'The Peekies', 'playlist' => null, 'video' => 'https://youtu.be/abc123']],
    ]);
    $listing->save();

    $lineUp = lineUpOf($listing);

    expect(array_column($lineUp, 'name'))->toBe(['Jackson Almond', 'The Peekies'])
        ->and($lineUp[0]['playlist'])->toContain('bandcamp.com/EmbeddedPlayer/album=1')
        ->and($lineUp[0]['video'])->toBeNull()
        ->and($lineUp[1]['video'])->toContain('youtube-nocookie.com/embed/abc123');
});

test('an embed from a provider not on the list is dropped, and an artist left with nothing goes too', function () {
    $listing = Listing::factory()->create(['line_up' => officeLineUp([
        ['name' => 'Half there', 'playlist' => '<iframe src="https://evil.example.com/x"></iframe>', 'video' => 'https://youtu.be/abc123'],
        ['name' => 'Not there', 'playlist' => '<iframe src="https://evil.example.com/y"></iframe>'],
    ])]);

    $lineUp = lineUpOf($listing);

    expect(array_column($lineUp, 'name'))->toBe(['Half there'])
        ->and($lineUp[0]['playlist'])->toBeNull()
        ->and($lineUp[0]['video'])->toContain('youtube-nocookie.com/embed/abc123');
});

test('an artist with no name is dropped, and the line-up stops at six', function () {
    $artists = [['name' => '  ', 'video' => 'https://youtu.be/nameless']];

    foreach (range(1, 7) as $number) {
        $artists[] = ['name' => "Act {$number}", 'video' => 'https://youtu.be/abc123'];
    }

    $listing = Listing::factory()->create(['line_up' => officeLineUp($artists)]);

    expect(array_column(lineUpOf($listing), 'name'))->toBe(['Act 1', 'Act 2', 'Act 3', 'Act 4', 'Act 5', 'Act 6']);
});

test('a line-up with nothing left in it is stored as none', function () {
    $listing = Listing::factory()->create(['line_up' => officeLineUp([['name' => 'Nobody', 'playlist' => null, 'video' => null]])]);

    expect($listing->fresh()->line_up)->toBeNull();
});

test('cleaning a clean line-up changes nothing, so saving again keeps it as it was', function () {
    $lineUps = app(ListingLineUp::class);
    $clean = $lineUps->clean(officeLineUp([['name' => 'Act', 'playlist' => BANDCAMP, 'video' => 'https://vimeo.com/76979871']]));

    expect($lineUps->clean($clean))->toBe($clean);
});

test('the migration carries each listing\'s single playlist and video over as one artist named after it', function () {
    Schema::table('listings', fn ($table) => $table->dropColumn('line_up'));

    $withBoth = Listing::factory()->create(['title' => 'Los Fastidios']);
    $videoOnly = Listing::factory()->create(['title' => 'The Peekies']);
    $refused = Listing::factory()->create(['title' => 'Bad paste']);
    $neither = Listing::factory()->create();

    DB::table('listings')->where('id', $withBoth->id)->update(['audio_playlist' => BANDCAMP, 'video_embed' => 'https://youtu.be/abc123']);
    DB::table('listings')->where('id', $videoOnly->id)->update(['video_embed' => 'https://vimeo.com/76979871']);
    DB::table('listings')->where('id', $refused->id)->update(['audio_playlist' => '<iframe src="https://evil.example.com/x"></iframe>']);

    (require database_path('migrations/2026_10_24_183149_add_line_up_to_listings_table.php'))->up();

    $both = lineUpOf($withBoth);

    expect($both)->toHaveCount(1)
        ->and($both[0]['name'])->toBe('Los Fastidios')
        ->and($both[0]['playlist'])->toContain('bandcamp.com/EmbeddedPlayer/album=1')
        ->and($both[0]['video'])->toContain('youtube-nocookie.com/embed/abc123')
        ->and(lineUpOf($videoOnly)[0]['playlist'])->toBeNull()
        ->and(lineUpOf($videoOnly)[0]['video'])->toContain('player.vimeo.com/video/76979871')
        ->and($refused->fresh()->line_up)->toBeNull()
        ->and($neither->fresh()->line_up)->toBeNull();
});
