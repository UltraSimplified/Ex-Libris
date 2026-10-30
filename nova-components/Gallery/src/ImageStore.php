<?php

namespace Ultrasimplified\Gallery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Stores one uploaded gallery image as a webp and a thumbnail.
 *
 * The browser has usually scaled the photo down already, so a camera's
 * original never reaches the server whole; it is scaled down here as well,
 * since nothing obliges a browser to do it.
 */
class ImageStore
{
    /**
     * @param  array{disk: string, directory: string, maxEdge: int, thumbnailEdge: int, quality: int}  $settings
     * @return array{image: string, thumb: string, width: int, height: int, alt: null, caption: null}
     */
    public function store(UploadedFile $file, array $settings): array
    {
        $image = Image::decodePath($file->getPathname())
            ->orient()
            ->scaleDown($settings['maxEdge'], $settings['maxEdge']);

        $width = $image->width();
        $height = $image->height();
        $full = (string) $image->encodeUsingFormat(Format::WEBP, quality: $settings['quality']);
        $thumbnail = (string) $image->scaleDown($settings['thumbnailEdge'], $settings['thumbnailEdge'])->encodeUsingFormat(Format::WEBP, quality: $settings['quality']);

        $key = $this->key();
        $path = $settings['directory'].'/'.$key.'.webp';
        $thumb = $settings['directory'].'/'.$key.'-thumb.webp';

        $disk = Storage::disk($settings['disk']);
        $disk->put($path, $full);
        $disk->put($thumb, $thumbnail);

        return [
            'image' => $path,
            'thumb' => $thumb,
            'width' => $width,
            'height' => $height,
            'alt' => null,
            'caption' => null,
        ];
    }

    /**
     * A key in the same format Nova's Flexible field generates: "c" followed
     * by 15 hex characters.
     */
    protected function key(): string
    {
        return 'c'.substr(bin2hex(random_bytes(8)), 0, 15);
    }
}
