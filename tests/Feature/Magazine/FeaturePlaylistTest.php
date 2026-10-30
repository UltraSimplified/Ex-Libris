<?php

use App\Models\Feature;
use App\Nova\Feature as NovaFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Whitecube\NovaFlexibleContent\Flexible;

/**
 * The embed as Bandcamp hands it out: a fixed 350px width, `seamless`, and an
 * anchor fallback inside the iframe.
 */
const PASTED_BANDCAMP = '<iframe style="border: 0; width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=3562433514/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/transparent=true/" seamless><a href="https://losfastidiosofficial.bandcamp.com/album/lovesteady">Lovesteady by Los Fastidios</a></iframe>';

/**
 * A Flexible body holding a single playlist layout, as Nova stores it.
 */
function featureBodyWithPlaylist(?string $playlist): string
{
    return json_encode([
        ['layout' => 'playlist', 'key' => 'abc123', 'attributes' => ['playlist' => $playlist]],
    ]);
}

/**
 * The playlist stored on the first layout of a feature's body.
 */
function storedPlaylist(Feature $feature): ?string
{
    return json_decode($feature->fresh()->body, true)[0]['attributes']['playlist'];
}

test('a pasted bandcamp embed is rebuilt before it is stored', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist(PASTED_BANDCAMP)]);

    $stored = storedPlaylist($feature);

    expect($stored)->toStartWith('<iframe ')
        ->and($stored)->toContain('width: 100%')
        ->and($stored)->toContain('height: 470px')
        ->and($stored)->toContain('album=3562433514')
        ->and($stored)->not->toContain('350px')
        ->and($stored)->not->toContain('seamless')
        ->and($stored)->not->toContain('<a ')
        ->and($stored)->not->toContain('losfastidiosofficial');
});

test('a playlist that cannot be used as an embed is stored as null', function (string $pasted) {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist($pasted)]);

    expect(storedPlaylist($feature))->toBeNull();
})->with([
    'an unsupported host' => '<iframe src="https://evil.example.com/player" style="height: 400px;"></iframe>',
    'a script' => '<script>alert(document.cookie)</script>',
    'a script beside a good iframe host' => '<script src="https://bandcamp.com/x.js"></script>',
    'a bare link' => 'https://artist.bandcamp.com/album/some-album',
    'plain text' => 'listen on bandcamp',
]);

test('every playlist layout in a body is cleaned, wherever it sits', function () {
    $body = json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Intro</p>']],
        ['layout' => 'playlist', 'key' => 'two', 'attributes' => ['playlist' => PASTED_BANDCAMP]],
        ['layout' => 'pullquote', 'key' => 'three', 'attributes' => ['quote' => 'Loud', 'cite' => 'Someone']],
        ['layout' => 'playlist', 'key' => 'four', 'attributes' => ['playlist' => '<script>x()</script>']],
    ]);

    $layouts = json_decode(Feature::factory()->create(['body' => $body])->fresh()->body, true);

    expect($layouts[1]['attributes']['playlist'])->toContain('width: 100%')
        ->and($layouts[3]['attributes']['playlist'])->toBeNull()
        ->and($layouts[0])->toBe(['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Intro</p>']])
        ->and($layouts[2])->toBe(['layout' => 'pullquote', 'key' => 'three', 'attributes' => ['quote' => 'Loud', 'cite' => 'Someone']]);
});

test('a body with no playlist is stored exactly as it was given', function () {
    // Pretty-printed with unescaped slashes, which json_encode would not
    // reproduce, so any re-encoding of the body shows up as a difference.
    $body = json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>See https://example.com/a</p>']],
        ['layout' => 'divider', 'key' => 'two', 'attributes' => []],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $feature = Feature::factory()->create(['body' => $body]);

    expect($feature->fresh()->body)->toBe($body);
});

test('other layouts are left alone even when they mention a playlist', function () {
    $body = json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['playlist' => PASTED_BANDCAMP]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $feature = Feature::factory()->create(['body' => $body]);

    expect($feature->fresh()->body)->toBe($body);
});

test('a body that is not a list of layouts is left alone', function (string $body) {
    $feature = Feature::factory()->create(['body' => $body]);

    expect($feature->fresh()->body)->toBe($body);
})->with([
    'not json' => 'this is not json',
    'an empty list' => '[]',
    'a json string' => '"playlist"',
    'a json object' => '{"playlist":"x"}',
    'layouts without attributes' => '[{"layout":"playlist","key":"a"}]',
]);

test('a playlist that is not text is stored as null', function () {
    $body = '[{"layout":"playlist","key":"a","attributes":{"playlist":{"src":"x"}}}]';

    $feature = Feature::factory()->create(['body' => $body]);

    expect(storedPlaylist($feature))->toBeNull();
});

test('an empty playlist stays empty', function (?string $empty) {
    $body = json_encode(
        [['layout' => 'playlist', 'key' => 'abc123', 'attributes' => ['playlist' => $empty]]],
        JSON_PRETTY_PRINT,
    );

    $feature = Feature::factory()->create(['body' => $body]);

    expect($feature->fresh()->body)->toBe($body)
        ->and(storedPlaylist($feature))->toBe($empty);
})->with([
    'null' => null,
    'an empty string' => '',
    'only spaces' => '   ',
]);

test('saving an already clean playlist again changes nothing', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist(PASTED_BANDCAMP)]);
    $clean = storedPlaylist($feature);

    // Resaved as Nova would, with the body re-posted and so marked dirty, but
    // formatted differently so the string itself is new.
    $resaved = json_encode([
        ['layout' => 'playlist', 'key' => 'abc123', 'attributes' => ['playlist' => $clean]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $feature = $feature->fresh();
    $feature->body = $resaved;
    $feature->save();

    expect($feature->fresh()->body)->toBe($resaved)
        ->and(storedPlaylist($feature))->toBe($clean);
});

test('a playlist cleaned a second time comes out the same as the first', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist(PASTED_BANDCAMP)]);
    $once = storedPlaylist($feature);

    $feature->body = featureBodyWithPlaylist($once);
    $feature->save();

    expect(storedPlaylist($feature))->toBe($once);
});

test('saving a feature without changing its body leaves the body alone', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist(null)]);

    // A raw playlist written behind the model's back, the way Feature 13 was
    // saved before the playlist layout was sanitised.
    DB::table('features')
        ->where('id', $feature->id)
        ->update(['body' => featureBodyWithPlaylist(PASTED_BANDCAMP)]);

    $feature = $feature->fresh();
    $feature->title = 'A Different Title';
    $feature->save();

    expect($feature->fresh()->body)->toBe(featureBodyWithPlaylist(PASTED_BANDCAMP))
        ->and($feature->fresh()->title)->toBe('A Different Title');
});

test('editing the body cleans a playlist that was stored raw', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithPlaylist(null)]);

    DB::table('features')
        ->where('id', $feature->id)
        ->update(['body' => featureBodyWithPlaylist(PASTED_BANDCAMP)]);

    $feature = $feature->fresh();
    $feature->body = featureBodyWithPlaylist(PASTED_BANDCAMP.' ');
    $feature->save();

    expect(storedPlaylist($feature))->toContain('width: 100%')
        ->and(storedPlaylist($feature))->not->toContain('seamless');
});

/**
 * The playlist textarea inside the Body field's Audio Playlist layout.
 */
function novaPlaylistField(): Field
{
    $body = collect((new NovaFeature(new Feature))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Flexible);

    return $body->meta()['layouts']->find('playlist')->fields()[0];
}

test('nova turns away a playlist that is not an embed from a supported provider', function (string $pasted) {
    $validator = Validator::make(
        ['playlist' => $pasted],
        novaPlaylistField()->getRules(app(NovaRequest::class)),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('playlist'))->toContain('Bandcamp, Spotify, Tidal or Qobuz');
})->with([
    'an unsupported host' => '<iframe src="https://evil.example.com/player"></iframe>',
    'a script' => '<script>alert(1)</script>',
    'a bare link' => 'https://artist.bandcamp.com/album/some-album',
]);

test('nova accepts a pasted embed, and a playlist left empty', function (?string $pasted) {
    $validator = Validator::make(
        ['playlist' => $pasted],
        novaPlaylistField()->getRules(app(NovaRequest::class)),
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'a bandcamp embed' => PASTED_BANDCAMP,
    'nothing' => null,
    'an empty string' => '',
]);

test('the playlist field says what to paste', function () {
    expect(novaPlaylistField()->getHelpText())->toContain('embed code');
});

/**
 * Nova's Flexible field does not hand the model a JSON string: its resolver
 * assigns a collection of layouts to the attribute, and Eloquent serialises
 * it on the way to the database. This is the shape a real Nova save arrives
 * in, so the playlist has to be cleaned from it as well.
 */
test('a body assigned as a collection of layouts, the way nova assigns it, is cleaned', function () {
    $feature = Feature::factory()->create();

    $feature->body = collect([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Intro</p>']],
        ['layout' => 'playlist', 'key' => 'two', 'attributes' => ['playlist' => PASTED_BANDCAMP]],
    ]);
    $feature->save();

    $layouts = json_decode($feature->fresh()->body, true);

    expect($layouts[0])->toBe(['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Intro</p>']])
        ->and($layouts[1]['attributes']['playlist'])->toContain('width: 100%')
        ->and($layouts[1]['attributes']['playlist'])->not->toContain('seamless')
        ->and($layouts[1]['attributes']['playlist'])->not->toContain('<a ');
});

test('a collection body with a playlist that cannot be used stores null for it', function () {
    $feature = Feature::factory()->create();

    $feature->body = collect([
        ['layout' => 'playlist', 'key' => 'two', 'attributes' => ['playlist' => '<script>alert(1)</script>']],
    ]);
    $feature->save();

    expect(storedPlaylist($feature))->toBeNull();
});

test('a collection body with no playlist is saved the way it always was', function () {
    $feature = Feature::factory()->create();
    $layouts = [['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Intro</p>']]];

    $feature->body = collect($layouts);
    $feature->save();

    expect(json_decode($feature->fresh()->body, true))->toBe($layouts);
});
