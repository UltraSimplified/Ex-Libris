<?php

namespace App\Services\Images;

use App\Models\Festival;
use App\Models\Promoter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Prepares and stores the pictures a promoter or a venue is known by.
 *
 * Output matches the Nova ImageCropper fields on those two resources so
 * both entry points write interchangeable files: a webp at the root of the
 * bucket, referenced by filename alone and served through the CDN.
 *
 * A logo is cropped square, filled from the middle. A promoter's banner
 * and a venue's cover photo head their pages at their own height, so
 * they keep the shape they were made in.
 */
final class BrandImage
{
    public const BANNER_WIDTH = 1280;

    public const LOGO_SIZE = 512;

    private const QUALITY = 85;

    private const DISK = 'do';

    /**
     * Store a promoter's banner and return the filename to persist. It is
     * kept in its own shape, as a venue's cover photo is.
     */
    public function banner(UploadedFile $file): string
    {
        return $this->cover($file);
    }

    /**
     * Store a venue's cover photo and return the filename to persist.
     *
     * The venue page sets it at its own height, so it keeps the shape it
     * was taken in: brought down to the banner's width, never cropped and
     * never enlarged.
     */
    public function cover(UploadedFile $file): string
    {
        $encoded = Image::decodePath($file->getPathname())
            ->orient()
            ->scaleDown(width: self::BANNER_WIDTH)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);

        return $this->put((string) $encoded);
    }

    /**
     * Store a logo and return the filename to persist.
     */
    public function logo(UploadedFile $file): string
    {
        return $this->store($file, self::LOGO_SIZE, self::LOGO_SIZE);
    }

    /**
     * Save a promoter or a festival with the logo and banner its form sent:
     * a new upload replaces the picture, a ticked remove box clears it, and
     * anything else leaves it be.
     *
     * The pictures replaced only go once the row pointing at the new ones
     * has saved, so a failure leaves nothing broken.
     */
    public function saveWithPictures(Promoter|Festival $owner, Request $request): void
    {
        $previous = ['logo' => $owner->logo, 'banner' => $owner->banner];

        if ($request->hasFile('logo')) {
            $owner->logo = $this->logo($request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $owner->logo = null;
        }

        if ($request->hasFile('banner')) {
            $owner->banner = $this->banner($request->file('banner'));
        } elseif ($request->boolean('remove_banner')) {
            $owner->banner = null;
        }

        $owner->save();

        foreach ($previous as $field => $filename) {
            if ($owner->wasChanged($field)) {
                $this->delete($filename);
            }
        }
    }

    /**
     * Give a new festival its own copies of the logo and banner of the one
     * it was started from, for each the form neither replaced nor removed.
     *
     * Copies rather than the same filenames: deleting a festival deletes
     * its pictures, which would strip them off the other one.
     */
    public function copyPicturesFrom(Festival $earlier, Festival $festival, Request $request): void
    {
        foreach (['logo', 'banner'] as $picture) {
            if (! $request->hasFile($picture) && ! $request->boolean('remove_'.$picture)) {
                $festival->{$picture} = $this->copy($earlier->{$picture});
            }
        }
    }

    /**
     * Copy a stored picture to a file of its own and return the new name,
     * or null when there is nothing there to copy.
     *
     * Anything absolute is not deleted from here, so it is safe to share.
     */
    public function copy(?string $filename): ?string
    {
        if (blank($filename) || $this->isElsewhere($filename)) {
            return $filename;
        }

        $contents = Storage::disk(self::DISK)->get($filename);

        return $contents === null ? null : $this->put($contents);
    }

    /**
     * Remove a previously stored image, ignoring one that has already gone.
     *
     * Anything absolute was put somewhere else by somebody else and is left
     * where it is.
     */
    public function delete(?string $filename): void
    {
        if (blank($filename) || $this->isElsewhere($filename)) {
            return;
        }

        Storage::disk(self::DISK)->delete($filename);
    }

    /**
     * Whether a stored value is a full address rather than a file on the
     * disk.
     */
    private function isElsewhere(string $filename): bool
    {
        return preg_match('#^(https?:)?//#i', $filename) === 1;
    }

    private function store(UploadedFile $file, int $width, int $height): string
    {
        $encoded = Image::decodePath($file->getPathname())
            ->orient()
            ->cover($width, $height)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);

        return $this->put((string) $encoded);
    }

    /**
     * Put an encoded webp on the disk under a fresh name and return it.
     */
    private function put(string $encoded): string
    {
        $filename = Str::random(40).'.webp';

        Storage::disk(self::DISK)->put($filename, $encoded);

        return $filename;
    }
}
