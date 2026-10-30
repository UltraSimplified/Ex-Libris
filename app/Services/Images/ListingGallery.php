<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Maintains a listing's gallery, stored in the JSON shape that Nova's
 * Flexible Content field writes so listings stay editable from both the
 * dashboard and Nova, and the existing front-end lightbox reads either
 * without changes:
 *
 *     [{"layout":"image","key":"c0f3a…","attributes":{"image":"gallery/x.webp","caption":"…"}}]
 *
 * Images are stored under gallery/ on the Spaces disk, matching the Nova
 * field's own store() callback.
 */
final class ListingGallery
{
    /**
     * Longest edge for a gallery image. The lightbox shows these full width
     * on a phone and around 900px on a desktop, so anything beyond this is
     * bandwidth nobody sees.
     */
    private const MAX_EDGE = 1600;

    private const QUALITY = 82;

    private const DISK = 'do';

    private const DIRECTORY = 'gallery';

    /**
     * Decode a listing's stored gallery into an array of groups.
     *
     * @return array<int, array{layout: string, key: string, attributes: array{image: string, caption: ?string}}>
     */
    public function parse(?string $stored): array
    {
        if (blank($stored)) {
            return [];
        }

        $decoded = json_decode($stored, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            fn ($group): bool => is_array($group) && filled($group['attributes']['image'] ?? null)
        ));
    }

    /**
     * Encode groups back to the column, collapsing an empty gallery to null.
     *
     * @param  array<int, array<string, mixed>>  $groups
     */
    public function encode(array $groups): ?string
    {
        return $groups === [] ? null : json_encode(array_values($groups));
    }

    /**
     * Build a group for a newly uploaded image.
     *
     * @return array{layout: string, key: string, attributes: array{image: string, caption: ?string}}
     */
    public function add(UploadedFile $file, ?string $caption = null): array
    {
        $image = Image::decodePath($file->getPathname())
            ->orient()
            ->scaleDown(self::MAX_EDGE, self::MAX_EDGE)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);

        $key = $this->key();
        $path = self::DIRECTORY.'/'.$key.'.webp';

        Storage::disk(self::DISK)->put($path, (string) $image);

        return [
            'layout' => 'image',
            'key' => $key,
            'attributes' => [
                'image' => $path,
                'caption' => filled($caption) ? $caption : null,
            ],
        ];
    }

    /**
     * Copy the stored files for the given groups, keys and all.
     *
     * As with a thumbnail, a duplicated listing gets its own files: they
     * are deleted with the listing that holds them, and a shared path
     * would empty the other one's gallery.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public function copy(array $groups): array
    {
        $disk = Storage::disk(self::DISK);

        return collect($groups)
            ->map(function (array $group) use ($disk): ?array {
                $path = $group['attributes']['image'] ?? null;

                if (blank($path) || ! $disk->exists($path)) {
                    return null;
                }

                $key = $this->key();
                $copy = self::DIRECTORY.'/'.$key.'.webp';
                $disk->copy($path, $copy);

                return [
                    ...$group,
                    'key' => $key,
                    'attributes' => [...$group['attributes'], 'image' => $copy],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Delete the stored files for the given groups.
     *
     * @param  array<int, array<string, mixed>>  $groups
     */
    public function delete(array $groups): void
    {
        foreach ($groups as $group) {
            $path = $group['attributes']['image'] ?? null;

            if (filled($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
    }

    /**
     * A key in the same format Nova's Flexible field generates: "c" followed
     * by 15 hex characters.
     */
    private function key(): string
    {
        return 'c'.substr(bin2hex(random_bytes(8)), 0, 15);
    }
}
