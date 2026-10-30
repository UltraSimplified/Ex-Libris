<?php

namespace App\Services\Listings;

use App\Services\Embeds\EmbedSanitiser;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A listing's line-up: the artists on the bill, each with a playlist, a
 * video or both, in the order they are shown.
 *
 * It is stored in the JSON shape Nova's Flexible Content field writes, as
 * the gallery is, so the Office edits the column directly and the listing
 * page reads either without a conversion in between:
 *
 *     [{"layout":"artist","key":"c0f3a…","attributes":{"name":"…","playlist":"<iframe…>","video":null}}]
 *
 * Whatever writes it, clean() runs as the listing saves, so only a named
 * artist with at least one embed rebuilt by the EmbedSanitiser is ever
 * kept: listings render these with v-html.
 */
final class ListingLineUp
{
    /**
     * The most artists one listing carries.
     */
    public const MAX_ARTISTS = 6;

    /**
     * The longest name kept.
     */
    public const MAX_NAME = 120;

    private const LAYOUT = 'artist';

    public function __construct(private EmbedSanitiser $embeds) {}

    /**
     * Decode a stored line-up into its groups, ignoring anything that is
     * not one.
     *
     * It arrives as the column's JSON, as an array, or - from the Office -
     * as the Collection of groups Nova's Flexible field sets on the listing
     * before it is saved, which is read the same way.
     *
     * @return array<int, array{layout: string, key: string, attributes: array<string, mixed>}>
     */
    public function parse(mixed $stored): array
    {
        $decoded = match (true) {
            is_string($stored) => json_decode($stored, true),
            $stored instanceof Arrayable, $stored instanceof JsonSerializable => json_decode(json_encode($stored), true),
            default => $stored,
        };

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            fn ($group): bool => is_array($group) && is_array($group['attributes'] ?? null),
        ));
    }

    /**
     * The line-up as artists, for a form to edit.
     *
     * @return array<int, array{name: string, playlist: ?string, video: ?string}>
     */
    public function artists(mixed $stored): array
    {
        return array_map(fn (array $group): array => [
            'name' => (string) ($group['attributes']['name'] ?? ''),
            'playlist' => $group['attributes']['playlist'] ?? null,
            'video' => $group['attributes']['video'] ?? null,
        ], $this->parse($stored));
    }

    /**
     * Rebuild a stored line-up so that only what is safe and complete is
     * kept: each embed rebuilt by the sanitiser or dropped, each artist
     * named and with something to play, no more than six, and nothing but
     * the three attributes. Collapses an empty line-up to null.
     */
    public function clean(mixed $stored): ?string
    {
        $groups = [];

        foreach ($this->parse($stored) as $group) {
            $attributes = $group['attributes'];
            $name = mb_substr(trim((string) ($attributes['name'] ?? '')), 0, self::MAX_NAME);
            $playlist = $this->embeds->audio($this->text($attributes['playlist'] ?? null));
            $video = $this->embeds->video($this->text($attributes['video'] ?? null));

            if ($name === '' || ($playlist === null && $video === null)) {
                continue;
            }

            $groups[] = [
                'layout' => self::LAYOUT,
                'key' => is_string($group['key'] ?? null) && $group['key'] !== '' ? $group['key'] : $this->key(),
                'attributes' => ['name' => $name, 'playlist' => $playlist, 'video' => $video],
            ];
        }

        $groups = array_slice($groups, 0, self::MAX_ARTISTS);

        return $groups === [] ? null : json_encode($groups);
    }

    /**
     * The line-up from the dashboard form, as submitted, for clean() to
     * vet when the listing saves.
     *
     * @param  array<int, array<string, mixed>>|null  $artists
     * @return array<int, array{layout: string, key: string, attributes: array<string, mixed>}>
     */
    public function fromForm(?array $artists): array
    {
        return array_map(fn (array $artist): array => [
            'layout' => self::LAYOUT,
            'key' => $this->key(),
            'attributes' => [
                'name' => $artist['name'] ?? null,
                'playlist' => $artist['playlist'] ?? null,
                'video' => $artist['video'] ?? null,
            ],
        ], array_values($artists ?? []));
    }

    /**
     * A one-artist line-up from the single playlist and video a listing
     * held before line-ups, named after the listing, or null when it had
     * neither or neither survives the sanitiser.
     */
    public function fromSingleEmbeds(string $name, ?string $playlist, ?string $video): ?string
    {
        return $this->clean([[
            'layout' => self::LAYOUT,
            'key' => $this->key(),
            'attributes' => ['name' => $name, 'playlist' => $playlist, 'video' => $video],
        ]]);
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * A key in the style Flexible gives its groups.
     */
    private function key(): string
    {
        return 'c'.substr(bin2hex(random_bytes(8)), 0, 15);
    }
}
