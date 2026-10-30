<?php

use App\Models\Feature;

/**
 * YouTube's own embed code: a bare iframe fixed at 560 by 315.
 */
function youtubeEmbedAsPasted(): string
{
    return '<iframe width="560" height="315" src="https://www.youtube.com/embed/5k69qXrK7X8?si=N9HYRPHgXzVoh6md" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
}

/**
 * Vimeo's own embed code: the iframe inside a padded wrapper, then a script
 * that never runs from inside the page.
 */
function vimeoEmbedAsPasted(): string
{
    return '<div style="padding:56.25% 0 0 0;position:relative;"><iframe src="https://player.vimeo.com/video/76979871?badge=0&amp;autopause=0&amp;player_id=0&amp;app_id=58479" frameborder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerpolicy="strict-origin-when-cross-origin" style="position:absolute;top:0;left:0;width:100%;height:100%;" title="The New Vimeo Player"></iframe></div><script src="https://player.vimeo.com/api/player.js"></script>';
}

/**
 * A feature body with a video between two paragraphs, as Nova stores it.
 */
function featureBodyAroundVideo(?string $video): string
{
    return json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Before the video.</p>']],
        ['layout' => 'video', 'key' => 'two', 'attributes' => ['video' => $video]],
        ['layout' => 'copy', 'key' => 'three', 'attributes' => ['text' => '<p>After the video.</p>']],
    ]);
}

/**
 * The layout read the embed from the top of the layout rather than from its
 * attributes, where Nova keeps it, so feature 16 showed nothing at all.
 */
test('a video layout is shown as a player between the text around it', function (string $embed, string $src) {
    $feature = Feature::factory()->create(['body' => featureBodyAroundVideo($embed)]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the video.')
        ->assertPresent(".feature-body iframe[src^=\"{$src}\"]")
        ->assertSee('After the video.')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
})->with([
    'YouTube' => [fn () => youtubeEmbedAsPasted(), 'https://www.youtube.com/embed/5k69qXrK7X8'],
    'Vimeo' => [fn () => vimeoEmbedAsPasted(), 'https://player.vimeo.com/video/76979871'],
]);

test('a video fills the column at sixteen by nine', function (string $embed) {
    $feature = Feature::factory()->create(['body' => featureBodyAroundVideo($embed)]);

    $filled = <<<'JS'
        (() => {
            const column = document.querySelector('.feature-body iframe').closest('.feature-body > *');
            const player = document.querySelector('.feature-body iframe').getBoundingClientRect();

            return player.width > 560
                && Math.round(player.width) === Math.round(column.getBoundingClientRect().width)
                && Math.abs(player.width / player.height - 16 / 9) < 0.02;
        })()
        JS;

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertPresent('.feature-body iframe')
        ->assertScript($filled)
        ->assertNoJavaScriptErrors();
})->with([
    'YouTube' => [fn () => youtubeEmbedAsPasted()],
    'Vimeo' => [fn () => vimeoEmbedAsPasted()],
]);

test('a video left empty draws nothing', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundVideo(null)]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the video.')
        ->assertSee('After the video.')
        ->assertMissing('.feature-body iframe')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
});
