<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * A small JPEG of a product picture, for the order emails.
 *
 * Product pictures are stored as webp, which Outlook on Windows and
 * Outlook.com will not render: a reader there would get a broken image
 * beside every line of their receipt. So the emails point at a JPEG copy
 * instead, made the first time one is asked for and kept on the same
 * DigitalOcean Spaces disk as the original, served through the CDN.
 *
 * The whole picture is scaled to fit and the leftover space padded white,
 * rather than cropped square: a print or a poster is often tall, and the
 * white is invisible against the email's own background.
 *
 * The copy is named after the source, so live and staging - which share
 * the bucket but not a database - land on one file rather than two.
 */
final class ProductThumbnail
{
    private const SIZE = 160;

    private const QUALITY = 80;

    private const DISK = 'do';

    private const PATH = 'order-thumbnails';

    /**
     * Where the JPEG copy of a product picture is, making it if it is not
     * there yet. Null when there is no picture, the original has gone from
     * the bucket, or it cannot be read as an image.
     */
    public function url(?string $image): ?string
    {
        if (blank($image)) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $path = self::PATH.'/'.hash('xxh128', $image).'-'.self::SIZE.'.jpg';

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        $original = $disk->get($image);

        if ($original === null) {
            return null;
        }

        try {
            $encoded = Image::decodeBinary($original)
                ->contain(self::SIZE, self::SIZE, 'ffffff')
                ->encodeUsingFormat(Format::JPEG, quality: self::QUALITY);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $disk->put($path, (string) $encoded);

        return $disk->url($path);
    }
}
