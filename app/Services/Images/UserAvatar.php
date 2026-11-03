<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Prepares and stores profile pictures on the DigitalOcean Spaces disk.
 *
 * Used by both Settings and the Office's Avatar field, so both entry points
 * are interchangeable: a webp under "avatars/", referenced by that path and
 * served through the CDN.
 *
 * The image is squared off rather than padded. A face shown in a circle is
 * cropped to a circle whatever we do, so covering the square loses nothing
 * that was going to survive, and the alternative - bars down the sides of a
 * portrait photograph - would be visible everywhere the avatar appears.
 *
 * SIZE is a ceiling: a picture smaller than that is squared off at its own
 * size rather than enlarged.
 */
final class UserAvatar
{
    private const SIZE = 250;

    private const QUALITY = 85;

    private const DISK = 'do';

    private const PATH = 'avatars';

    /**
     * Store the uploaded image and return the path to persist on the user.
     */
    public function store(UploadedFile $file): string
    {
        $encoded = Image::decodePath($file->getPathname())
            ->orient()
            ->coverDown(self::SIZE, self::SIZE)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);

        $path = self::PATH.'/'.Str::random(40).'.webp';

        Storage::disk(self::DISK)->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Remove a previously stored avatar, ignoring one that has already gone.
     *
     * Anything absolute belongs to somewhere else - an avatar service, or a
     * picture hosted outside the bucket - and is left where it is.
     */
    public function delete(?string $path): void
    {
        if (blank($path) || preg_match('#^(https?:)?//#i', $path) === 1) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
