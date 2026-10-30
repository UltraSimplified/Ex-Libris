<?php

namespace App\Services\Embeds;

use DOMDocument;
use DOMElement;

/**
 * Rebuilds third-party embed codes into a small, fixed iframe we control.
 *
 * Listings render these with v-html, so anything stored here is executed as
 * markup on the public site. Promoters paste embed codes from Bandcamp and
 * YouTube without reading them, and a paste from a compromised page is the
 * obvious way in — so nothing submitted is ever stored as given. The source
 * URL is checked against a provider allowlist and a fresh iframe is built
 * from a fixed set of attributes, discarding everything else. Sanitising on
 * save rather than on render means every consumer of the column is covered,
 * including Nova, SSR and anything reading the database directly.
 *
 * Width is always forced to 100% so a Bandcamp player pasted at its default
 * 350px fills the column it lands in.
 */
final class EmbedSanitiser
{
    /**
     * Providers accepted for the audio playlist field.
     */
    private const AUDIO_HOSTS = [
        'bandcamp.com',
        'spotify.com',
        'tidal.com',
        'qobuz.com',
    ];

    /**
     * Providers accepted for the video field.
     */
    private const VIDEO_HOSTS = [
        'youtube.com',
        'youtube-nocookie.com',
        'youtu.be',
        'vimeo.com',
    ];

    /**
     * Fallback height for an audio player that arrives without one.
     *
     * Matches Bandcamp's Standard layout with big artwork and the tracklist
     * shown, which is the house shape for listings, and the default the
     * front end already assumes.
     */
    private const DEFAULT_AUDIO_HEIGHT = '786px';

    /**
     * Clean an audio playlist embed, or return null when it is not usable.
     */
    public function audio(?string $input): ?string
    {
        $iframe = $this->readIframe($input, self::AUDIO_HOSTS);

        if ($iframe === null) {
            return null;
        }

        return $this->buildIframe(
            $iframe['src'],
            "border: 0; width: 100%; height: {$this->heightOf($iframe)};",
            $iframe['allow'],
            $iframe['allowfullscreen'],
        );
    }

    /**
     * Clean a video embed, or return null when it is not usable.
     *
     * Accepts a full embed code or a plain watch/share URL, since a promoter
     * copying the address bar is at least as likely as one using "Embed".
     */
    public function video(?string $input): ?string
    {
        $iframe = $this->readIframe($input, self::VIDEO_HOSTS)
            ?? $this->readBareUrl($input, self::VIDEO_HOSTS);

        if ($iframe === null) {
            return null;
        }

        return $this->buildIframe(
            $this->normaliseVideoSrc($iframe['src']),
            'border: 0; width: 100%; aspect-ratio: 16 / 9;',
            $iframe['allow'] ?? 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
            true,
        );
    }

    /**
     * Pull the first iframe out of a pasted embed code and vet its source.
     *
     * @param  array<int, string>  $hosts
     * @return array{src: string, style: ?string, height: ?string, allow: ?string, allowfullscreen: bool}|null
     */
    private function readIframe(?string $input, array $hosts): ?array
    {
        if (blank($input) || ! str_contains($input, '<iframe')) {
            return null;
        }

        $document = new DOMDocument;

        // Fragments of third-party markup are rarely well formed; parse
        // errors are expected and are not a reason to reject the embed.
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$input,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $iframe = $document->getElementsByTagName('iframe')->item(0);

        if (! $iframe instanceof DOMElement) {
            return null;
        }

        $src = trim($iframe->getAttribute('src'));

        if (! $this->hostIsAllowed($src, $hosts)) {
            return null;
        }

        return [
            'src' => $src,
            'style' => $iframe->getAttribute('style') ?: null,
            'height' => $iframe->getAttribute('height') ?: null,
            'allow' => $iframe->getAttribute('allow') ?: null,
            'allowfullscreen' => $iframe->hasAttribute('allowfullscreen'),
        ];
    }

    /**
     * Treat a bare URL as an embed source.
     *
     * @param  array<int, string>  $hosts
     * @return array{src: string, style: null, height: null, allow: null, allowfullscreen: bool}|null
     */
    private function readBareUrl(?string $input, array $hosts): ?array
    {
        $candidate = trim((string) $input);

        if ($candidate === '' || str_contains($candidate, '<')) {
            return null;
        }

        if (! $this->hostIsAllowed($candidate, $hosts)) {
            return null;
        }

        return [
            'src' => $candidate,
            'style' => null,
            'height' => null,
            'allow' => null,
            'allowfullscreen' => true,
        ];
    }

    /**
     * Is this URL an https address served by one of the allowed providers?
     *
     * @param  array<int, string>  $hosts
     */
    private function hostIsAllowed(string $url, array $hosts): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Point watch and share URLs at the provider's embed player.
     */
    private function normaliseVideoSrc(string $src): string
    {
        $parts = parse_url($src);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (str_ends_with($host, 'youtu.be')) {
            return $this->youtubeEmbed(trim($path, '/'));
        }

        if (str_contains($host, 'youtube')) {
            if (str_starts_with($path, '/watch') && isset($query['v'])) {
                return $this->youtubeEmbed((string) $query['v']);
            }

            if (str_starts_with($path, '/shorts/') || str_starts_with($path, '/live/')) {
                return $this->youtubeEmbed(basename($path));
            }

            if (str_starts_with($path, '/embed/')) {
                return $this->youtubeEmbed(basename($path));
            }
        }

        if (str_contains($host, 'vimeo') && ! str_starts_with($host, 'player.')) {
            if (preg_match('#^/(\d+)#', $path, $matches) === 1) {
                return 'https://player.vimeo.com/video/'.$matches[1];
            }
        }

        return $src;
    }

    /**
     * Build a privacy-mode YouTube embed URL for a video id.
     */
    private function youtubeEmbed(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id);
    }

    /**
     * Work out the height to keep for an audio player: an inline style wins,
     * then a height attribute, then a sensible default for a large player.
     *
     * @param  array{style: ?string, height: ?string}  $iframe
     */
    private function heightOf(array $iframe): string
    {
        if ($iframe['style'] !== null
            && preg_match('/height:\s*([\d.]+(?:px|%)?)/i', $iframe['style'], $matches) === 1) {
            return $this->withUnit($matches[1]);
        }

        if ($iframe['height'] !== null && preg_match('/^[\d.]+(px|%)?$/', $iframe['height']) === 1) {
            return $this->withUnit($iframe['height']);
        }

        return self::DEFAULT_AUDIO_HEIGHT;
    }

    /**
     * Add a px unit to a bare number.
     */
    private function withUnit(string $value): string
    {
        return preg_match('/^\d+(\.\d+)?$/', $value) === 1 ? $value.'px' : $value;
    }

    /**
     * Assemble the iframe from the handful of attributes we are willing to emit.
     */
    private function buildIframe(string $src, string $style, ?string $allow, bool $allowfullscreen): string
    {
        $attributes = [
            'src' => $src,
            'style' => $style,
            'loading' => 'lazy',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
        ];

        if ($allow !== null) {
            $attributes['allow'] = $allow;
        }

        $html = '<iframe';

        foreach ($attributes as $name => $value) {
            $html .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
        }

        if ($allowfullscreen) {
            $html .= ' allowfullscreen';
        }

        return $html.'></iframe>';
    }
}
