<?php

use App\Models\Feature;
use App\Models\User;

const PREVIEWED_VIDEO = '.video-embed__frame iframe';
const VIDEO_EMBED_BOX = '.video-embed textarea';

/**
 * A feature whose body is a single Video layout holding the given code.
 */
function featureWithVideoLayout(?string $code): Feature
{
    return Feature::factory()->create(['body' => json_encode([
        ['layout' => 'video', 'key' => 'cVideo0000000001', 'attributes' => ['video' => $code]],
    ])]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

test('a saved YouTube embed is previewed above its code in the editor', function () {
    $feature = featureWithVideoLayout('<iframe width="560" height="315" src="https://www.youtube.com/embed/5k69qXrK7X8?si=abc" allowfullscreen></iframe>');

    visit("/office/resources/features/{$feature->id}/edit")
        ->assertAttribute(PREVIEWED_VIDEO, 'src', 'https://www.youtube.com/embed/5k69qXrK7X8?si=abc')
        ->assertScript(<<<'JS'
            (() => {
                const preview = document.querySelector('.video-embed__frame');
                const code = document.querySelector('.video-embed textarea');

                return Boolean(preview.compareDocumentPosition(code) & Node.DOCUMENT_POSITION_FOLLOWING);
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

test('the preview follows the code as it is pasted in', function () {
    $feature = featureWithVideoLayout(null);

    visit("/office/resources/features/{$feature->id}/edit")
        ->assertMissing(PREVIEWED_VIDEO)
        ->fill(VIDEO_EMBED_BOX, '<iframe src="https://player.vimeo.com/video/76979871?h=8272103f6e" width="640" height="360"></iframe>')
        ->wait(1)
        ->assertAttribute(PREVIEWED_VIDEO, 'src', 'https://player.vimeo.com/video/76979871?h=8272103f6e')
        ->assertNoJavaScriptErrors();
});

test('a link to a video says to paste its embed code instead', function () {
    $feature = featureWithVideoLayout(null);

    visit("/office/resources/features/{$feature->id}/edit")
        ->fill(VIDEO_EMBED_BOX, 'https://www.youtube.com/watch?v=5k69qXrK7X8')
        ->wait(1)
        ->assertMissing(PREVIEWED_VIDEO)
        ->assertSee('That is a link to the video, not its embed code.')
        ->assertNoJavaScriptErrors();
});

test('an embed from anywhere else is never put on the page', function () {
    $feature = featureWithVideoLayout(null);

    visit("/office/resources/features/{$feature->id}/edit")
        ->fill(VIDEO_EMBED_BOX, '<iframe src="https://elsewhere.example/embed/x"></iframe><img src="x" onerror="window.pastedMarkupRan = true">')
        ->wait(1)
        ->assertMissing(PREVIEWED_VIDEO)
        ->assertSee('This embed is not a YouTube or Vimeo player')
        ->assertScript('window.pastedMarkupRan === undefined', true)
        ->assertNoJavaScriptErrors();
});
