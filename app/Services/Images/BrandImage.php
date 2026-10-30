<?php

namespace App\Services\Images;

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
     * Remove a previously stored image, ignoring one that has already gone.
     *
     * Anything absolute was put somewhere else by somebody else and is left
     * where it is.
     */
    public function delete(?string $filename): void
    {
        if (blank($filename) || preg_match('#^(https?:)?//#i', $filename) === 1) {
            return;
        }

        Storage::disk(self::DISK)->delete($filename);
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
