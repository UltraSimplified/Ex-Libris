<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Colors\Rgb\Color as RgbColor;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\ColorInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Prepares and stores listing thumbnails on the DigitalOcean Spaces disk.
 *
 * Output matches what the Nova ImageCropper field produces so both entry
 * points write interchangeable files: a 548x462 webp at the root of the
 * bucket, referenced by filename alone and served through the CDN.
 *
 * Unlike the Nova field, nothing is cropped. Promoters upload posters and
 * flyers in whatever shape the designer handed them, so the whole graphic
 * is scaled to fit and the leftover space is padded with a colour sampled
 * from the edges of the image itself — on the flat-background artwork this
 * is nearly always given, the padding is invisible.
 */
final class ListingThumbnail
{
    private const WIDTH = 548;

    private const HEIGHT = 462;

    private const QUALITY = 85;

    private const DISK = 'do';

    /**
     * Number of sample points taken along each of the four edges.
     */
    private const EDGE_SAMPLES = 12;

    /**
     * Channel values are rounded into buckets this wide before being counted,
     * so that a background with slight gradient or JPEG noise still resolves
     * to a single dominant colour.
     */
    private const BUCKET_SIZE = 16;

    /**
     * Store the uploaded image and return the filename to persist on the listing.
     */
    public function store(UploadedFile $file): string
    {
        $image = Image::decodePath($file->getPathname())->orient();

        $encoded = $image
            ->contain(self::WIDTH, self::HEIGHT, $this->sampleEdgeColor($image))
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);

        $filename = Str::random(40).'.webp';

        Storage::disk(self::DISK)->put($filename, (string) $encoded);

        return $filename;
    }

    /**
     * Copy a stored thumbnail to a file of its own.
     *
     * A duplicated listing gets its own copy rather than sharing a path:
     * deleting a listing deletes the file behind it, so a shared path
     * would strip the picture off whichever copy was left.
     */
    public function copy(?string $filename): ?string
    {
        if (blank($filename)) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($filename)) {
            return null;
        }

        $copy = Str::random(40).'.webp';
        $disk->copy($filename, $copy);

        return $copy;
    }

    /**
     * Remove a previously stored thumbnail, ignoring one that has already gone.
     */
    public function delete(?string $filename): void
    {
        if (blank($filename)) {
            return;
        }

        Storage::disk(self::DISK)->delete($filename);
    }

    /**
     * Work out what colour to pad with by looking at the edges of the image.
     *
     * Samples are bucketed by colour and the most populous bucket wins, which
     * picks the flat background out of artwork whose edges are mostly one
     * colour rather than averaging it against a stray logo or border. The
     * average within the winning bucket is returned so the result is the
     * true colour rather than the rounded bucket value.
     */
    private function sampleEdgeColor(ImageInterface $image): ColorInterface
    {
        $samples = $this->edgeSamples($image);

        if ($samples === []) {
            return new RgbColor(255, 255, 255);
        }

        $transparent = 0;
        $buckets = [];

        foreach ($samples as $color) {
            if ($color->isTransparent()) {
                $transparent++;

                continue;
            }

            $red = $color->red()->value();
            $green = $color->green()->value();
            $blue = $color->blue()->value();

            $key = implode(':', [
                intdiv($red, self::BUCKET_SIZE),
                intdiv($green, self::BUCKET_SIZE),
                intdiv($blue, self::BUCKET_SIZE),
            ]);

            $buckets[$key] ??= ['count' => 0, 'red' => 0, 'green' => 0, 'blue' => 0];
            $buckets[$key]['count']++;
            $buckets[$key]['red'] += $red;
            $buckets[$key]['green'] += $green;
            $buckets[$key]['blue'] += $blue;
        }

        // Artwork that is transparent around the edges keeps its transparency
        // rather than gaining a white or black frame it never had.
        if ($transparent > count($samples) / 2) {
            return new RgbColor(255, 255, 255, 0);
        }

        if ($buckets === []) {
            return new RgbColor(255, 255, 255);
        }

        $winner = array_reduce(
            $buckets,
            fn (?array $carry, array $bucket): array => $carry === null || $bucket['count'] > $carry['count']
                ? $bucket
                : $carry
        );

        return new RgbColor(
            (int) round($winner['red'] / $winner['count']),
            (int) round($winner['green'] / $winner['count']),
            (int) round($winner['blue'] / $winner['count']),
        );
    }

    /**
     * Pick colours at evenly spaced points along all four edges of the image.
     *
     * @return array<int, ColorInterface>
     */
    private function edgeSamples(ImageInterface $image): array
    {
        $right = $image->width() - 1;
        $bottom = $image->height() - 1;

        if ($right < 0 || $bottom < 0) {
            return [];
        }

        $samples = [];

        for ($step = 0; $step < self::EDGE_SAMPLES; $step++) {
            $ratio = $step / (self::EDGE_SAMPLES - 1);
            $x = (int) round($ratio * $right);
            $y = (int) round($ratio * $bottom);

            foreach ([[$x, 0], [$x, $bottom], [0, $y], [$right, $y]] as [$pointX, $pointY]) {
                $samples[] = $image->colorAt($pointX, $pointY)->toColorspace(RgbColorspace::class);
            }
        }

        return $samples;
    }
}
