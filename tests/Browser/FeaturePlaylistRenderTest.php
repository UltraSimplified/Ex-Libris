<?php

use App\Models\Feature;
use Illuminate\Support\Facades\DB;

/**
 * Bandcamp's own embed code: a fixed 350px width, `seamless`, and a link
 * inside the iframe.
 */
function bandcampEmbedAsPasted(): string
{
    return '<iframe style="border: 0; width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=3562433514/size=large/" seamless><a href="https://losfastidiosofficial.bandcamp.com/album/lovesteady">Lovesteady by Los Fastidios</a></iframe>';
}

/**
 * A feature body with a playlist between two paragraphs, as Nova stores it.
 */
function featureBodyAroundPlaylist(?string $playlist): string
{
    return json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Before the player.</p>']],
        ['layout' => 'playlist', 'key' => 'two', 'attributes' => ['playlist' => $playlist]],
        ['layout' => 'copy', 'key' => 'three', 'attributes' => ['text' => '<p>After the player.</p>']],
    ]);
}

/**
 * The page had no renderer for the playlist layout, so it fell through to
 * the catch-all and printed the word OTHER over the layout's raw JSON.
 *
 * Only the page in a browser shows that: the props were right all along.
 */
test('a playlist layout is shown as a player between the text around it', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPlaylist(bandcampEmbedAsPasted())]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the player.')
        ->assertPresent('.feature-body iframe[src^="https://bandcamp.com/EmbeddedPlayer/album=3562433514"]')
        ->assertSee('After the player.')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
});

/**
 * Feature 13 was saved raw, before playlists were cleaned, and keeps
 * Bandcamp's 350px until somebody saves it again.
 */
test('a playlist saved before embeds were cleaned still fills the column', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPlaylist(null)]);

    DB::table('features')
        ->where('id', $feature->id)
        ->update(['body' => featureBodyAroundPlaylist(bandcampEmbedAsPasted())]);

    $filled = <<<'JS'
        (() => {
            const player = document.querySelector('.feature-body iframe');
            const width = Math.round(player.getBoundingClientRect().width);

            return width > 350
                && width === Math.round(player.parentElement.getBoundingClientRect().width);
        })()
        JS;

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertPresent('.feature-body iframe')
        ->assertScript($filled)
        ->assertNoJavaScriptErrors();
});

test('a playlist left empty draws nothing', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPlaylist(null)]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the player.')
        ->assertSee('After the player.')
        ->assertMissing('.feature-body iframe')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
});
