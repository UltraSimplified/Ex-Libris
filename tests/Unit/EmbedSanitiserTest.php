<?php

use App\Services\Embeds\EmbedSanitiser;

beforeEach(function () {
    $this->sanitiser = new EmbedSanitiser;
});

test('a bandcamp embed keeps its source and height but is widened to 100%', function () {
    $clean = $this->sanitiser->audio(<<<'HTML'
    <iframe style="border: 0; width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=3562433514/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/transparent=true/" seamless><a href="https://losfastidiosofficial.bandcamp.com/album/lovesteady">Lovesteady by Los Fastidios</a></iframe>
    HTML);

    expect($clean)->toContain('width: 100%')
        ->and($clean)->toContain('height: 470px')
        ->and($clean)->not->toContain('350px')
        ->and($clean)->toContain('album=3562433514')
        ->and($clean)->toContain('loading="lazy"');
});

test('the anchor fallback and other markup around the iframe is dropped', function () {
    $clean = $this->sanitiser->audio(<<<'HTML'
    <iframe style="width: 350px; height: 470px;" src="https://bandcamp.com/EmbeddedPlayer/album=1/"><a href="https://evil.example.com">x</a></iframe>
    HTML);

    expect($clean)->not->toContain('evil.example.com')
        ->and($clean)->not->toContain('<a ')
        ->and($clean)->toStartWith('<iframe ')
        ->and($clean)->toEndWith('></iframe>');
});

test('a height given as an attribute rather than a style is kept', function () {
    $clean = $this->sanitiser->audio(
        '<iframe src="https://open.spotify.com/embed/album/123" height="352"></iframe>'
    );

    expect($clean)->toContain('height: 352px');
});

test('an audio embed with no height falls back to the standard player height', function () {
    $clean = $this->sanitiser->audio('<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1/"></iframe>');

    expect($clean)->toContain('height: 786px');
});

test('the standard bandcamp layout keeps its full height', function () {
    $clean = $this->sanitiser->audio(<<<'HTML'
    <iframe style="border: 0; width: 350px; height: 786px;" src="https://bandcamp.com/EmbeddedPlayer/album=3562433514/size=large/bgcol=ffffff/linkcol=0687f5/transparent=true/" seamless><a href="https://losfastidiosofficial.bandcamp.com/album/lovesteady">Lovesteady by Los Fastidios</a></iframe>
    HTML);

    expect($clean)->toContain('height: 786px')
        ->and($clean)->toContain('width: 100%')
        ->and($clean)->not->toContain('350px')
        ->and($clean)->toContain('size=large')
        ->and($clean)->not->toContain('losfastidiosofficial');
});

test('whichever bandcamp layout is chosen, its own height is respected', function (string $height) {
    $clean = $this->sanitiser->audio(
        '<iframe style="border: 0; width: 350px; height: '.$height.';" src="https://bandcamp.com/EmbeddedPlayer/album=1/size=large/"></iframe>'
    );

    expect($clean)->toContain('height: '.$height);
})->with([
    'standard, artwork big, tracklist' => '786px',
    'standard, no tracklist' => '470px',
    'artwork only' => '350px',
    'slim' => '42px',
]);

test('a spotify embed keeps the height from its attribute', function () {
    $clean = $this->sanitiser->audio(<<<'HTML'
    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3?utm_source=generator" width="100%" height="352" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
    HTML);

    expect($clean)->toContain('open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3')
        ->and($clean)->toContain('width: 100%')
        ->and($clean)->toContain('height: 352px')
        ->and($clean)->toContain('allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"')
        ->and($clean)->toContain('allowfullscreen');
});

test('a tidal embed is accepted', function () {
    $clean = $this->sanitiser->audio(
        '<iframe src="https://embed.tidal.com/albums/152119837?disableAnalytics=true" width="100%" height="256" frameborder="0"></iframe>'
    );

    expect($clean)->toContain('embed.tidal.com/albums/152119837')
        ->and($clean)->toContain('width: 100%')
        ->and($clean)->toContain('height: 256px');
});

test('audio embeds from other hosts are rejected', function (string $html) {
    expect($this->sanitiser->audio($html))->toBeNull();
})->with([
    'unknown provider' => '<iframe src="https://evil.example.com/player"></iframe>',
    'lookalike domain' => '<iframe src="https://bandcamp.com.evil.example.com/x"></iframe>',
    'plain http' => '<iframe src="http://bandcamp.com/EmbeddedPlayer/album=1/"></iframe>',
    'javascript url' => '<iframe src="javascript:alert(1)"></iframe>',
    'no iframe at all' => '<script>alert(1)</script>',
    'bare text' => 'have a listen on bandcamp',
]);

test('script and event handler attributes never survive', function () {
    $clean = $this->sanitiser->audio(
        '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1/" onload="alert(1)" srcdoc="<script>alert(1)</script>"></iframe><script>alert(2)</script>'
    );

    expect($clean)->not->toContain('onload')
        ->and($clean)->not->toContain('srcdoc')
        ->and($clean)->not->toContain('script');
});

test('a youtube embed code is rebuilt against the privacy domain', function () {
    $clean = $this->sanitiser->video(
        '<iframe width="560" height="315" src="https://www.youtube.com/embed/YkyJPHmsJ3s?si=abc" frameborder="0" allowfullscreen></iframe>'
    );

    expect($clean)->toContain('youtube-nocookie.com/embed/YkyJPHmsJ3s')
        ->and($clean)->toContain('width: 100%')
        ->and($clean)->toContain('aspect-ratio: 16 / 9')
        ->and($clean)->toContain('allowfullscreen');
});

test('a plain video link is turned into a player', function (string $url, string $expected) {
    expect($this->sanitiser->video($url))->toContain($expected);
})->with([
    'watch url' => ['https://www.youtube.com/watch?v=YkyJPHmsJ3s', 'youtube-nocookie.com/embed/YkyJPHmsJ3s'],
    'share url' => ['https://youtu.be/YkyJPHmsJ3s', 'youtube-nocookie.com/embed/YkyJPHmsJ3s'],
    'short' => ['https://www.youtube.com/shorts/YkyJPHmsJ3s', 'youtube-nocookie.com/embed/YkyJPHmsJ3s'],
    'vimeo page' => ['https://vimeo.com/76979871', 'player.vimeo.com/video/76979871'],
    'vimeo player' => ['https://player.vimeo.com/video/76979871', 'player.vimeo.com/video/76979871'],
]);

test('video embeds from other hosts are rejected', function (string $input) {
    expect($this->sanitiser->video($input))->toBeNull();
})->with([
    'unknown provider' => '<iframe src="https://evil.example.com/embed/1"></iframe>',
    'lookalike domain' => 'https://youtube.com.evil.example.com/watch?v=1',
    'not a url' => 'search for it on youtube',
]);

test('an empty value stays empty', function () {
    expect($this->sanitiser->audio(null))->toBeNull()
        ->and($this->sanitiser->audio(''))->toBeNull()
        ->and($this->sanitiser->video(null))->toBeNull();
});
