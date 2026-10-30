<?php

namespace App\Services\Images;

use App\Models\Feature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The image files a feature's body refers to, and their clean-up.
 *
 * Two kinds of layout keep files on the Spaces disk: the Full width, Left
 * and Right image layouts, one image each at the root of the disk, and the
 * Photojournal, whose photos sit under features/galleries/ (FeatureGallery).
 * When a save drops one from the body, or the feature is deleted, its files
 * go too, unless another feature still shows them or one is a feature's
 * cover photo or feature image.
 */
final class FeatureImages
{
    public const DISK = 'do';

    /**
     * The layouts that hold a single image in an "image" attribute.
     *
     * @var list<string>
     */
    public const IMAGE_LAYOUTS = ['imgfull', 'imgleft', 'imgright'];

    public function __construct(private FeatureGallery $gallery) {}

    /**
     * Every file a feature body refers to.
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
            if (! is_array($layout) || ! in_array($layout['layout'] ?? null, self::IMAGE_LAYOUTS, true)) {
                continue;
            }

            $image = $layout['attributes']['image'] ?? null;

            if ($this->isStoredPath($image)) {
                $paths[] = $image;
            }
        }

        return array_values(array_unique([...$paths, ...$this->gallery->paths($body)]));
    }

    /**
     * Delete the given files, once the save that dropped them has gone
     * through, keeping any that are still in use elsewhere.
     *
     * @param  list<string>  $paths
     */
    public function deleteUnused(array $paths, Feature $feature): void
    {
        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths, $feature) {
            $unused = array_diff($paths, $this->pathsUsedElsewhere($paths, $feature));

            if ($unused !== []) {
                Storage::disk(self::DISK)->delete(array_values($unused));
            }
        });
    }

    /**
     * Which of the given files another feature's body refers to, or any
     * feature holds as its cover photo or feature image.
     *
     * Bodies are stored with their slashes escaped, so they are searched for
     * each file's name, which is unique, and then read properly.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function pathsUsedElsewhere(array $paths, Feature $feature): array
    {
        $inOtherBodies = Feature::query()
            ->whereKeyNot($feature->getKey())
            ->where(function ($query) use ($paths) {
                foreach ($paths as $path) {
                    $query->orWhere('body', 'like', '%'.basename($path).'%');
                }
            })
            ->pluck('body')
            ->flatMap(fn ($body): array => $this->paths($body));

        $asCovers = Feature::query()
            ->whereIn('cover_photo', $paths)
            ->orWhereIn('feature_image', $paths)
            ->get(['cover_photo', 'feature_image'])
            ->flatMap(fn (Feature $other): array => [$other->cover_photo, $other->feature_image]);

        return array_values(array_intersect($paths, [...$inOtherBodies, ...$asCovers]));
    }

    private function isStoredPath(mixed $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_contains($path, '..')
            && ! str_contains($path, '://');
    }
}
