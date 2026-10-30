<?php

namespace App\Services\Images;

use Ultrasimplified\Gallery\Gallery;

/**
 * The photos in a feature's Photojournal layouts.
 *
 * Each photo is stored as a webp and a thumbnail under features/galleries/
 * on the Spaces disk, by the Nova Gallery field's upload route. The layout
 * holds the list in the shape the field writes:
 *
 *     {"layout":"gallery","key":"c0f3a…","attributes":{"images":[
 *         {"image":"features/galleries/c1a2….webp","thumb":"features/galleries/c1a2…-thumb.webp",
 *          "width":2000,"height":1333,"alt":"…","caption":"…"}]}}
 *
 * A path outside that directory is never taken for a photo, so nothing
 * else on the disk can be cleared away as one. FeatureImages deletes the
 * photos a feature no longer shows.
 */
final class FeatureGallery
{
    public const DISK = 'do';

    public const DIRECTORY = 'features/galleries';

    /**
     * Longest edge of a stored photo. The feature column is 726px wide, so
     * this is sharp on a high-density screen with room to spare.
     */
    public const MAX_EDGE = 2000;

    /**
     * Longest edge of a thumbnail, shown about 100px wide under the photo.
     */
    public const THUMBNAIL_EDGE = 400;

    /**
     * Every gallery file a feature body refers to, photos and thumbnails.
     *
     * The body is read as the JSON string it is stored as, or as the
     * collection of layouts Nova hands over while saving.
     *
     * @return list<string>
     */
    public function paths(mixed $body): array
    {
        $layouts = is_string($body) ? json_decode($body, true) : json_decode((string) json_encode($body), true);

        if (! is_array($layouts)) {
            return [];
        }

        $paths = [];

        foreach ($layouts as $layout) {
            if (! is_array($layout) || ($layout['layout'] ?? null) !== 'gallery') {
                continue;
            }

            foreach (Gallery::images($layout['attributes']['images'] ?? null, self::DIRECTORY) as $image) {
                $paths[] = $image['image'];
                $paths[] = $image['thumb'];
            }
        }

        return array_values(array_unique($paths));
    }
}
