<?php

namespace Ultrasimplified\Gallery;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Support\Fluent;

/**
 * A gallery of images, held in order as a list in one attribute.
 *
 * Each image is uploaded on its own the moment it is chosen, to the field's
 * upload route, which scales it down and stores a webp and a thumbnail. So
 * the form only ever sends the list itself, as JSON, however many photos
 * there are, and the server's limits on one request never come into it.
 *
 * Stored as:
 *
 *     [{"image":"gallery/c0f3a….webp","thumb":"gallery/c0f3a…-thumb.webp",
 *       "width":2000,"height":1333,"alt":"…","caption":"…"}]
 */
class Gallery extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'gallery';

    /**
     * Indicates if the element should be shown on the index view.
     *
     * @var bool
     */
    public $showOnIndex = false;

    protected string $disk = 'public';

    protected string $directory = 'gallery';

    /**
     * Longest edge of a stored image, in pixels.
     */
    protected int $maxEdge = 2000;

    /**
     * Longest edge of a stored thumbnail, in pixels.
     */
    protected int $thumbnailEdge = 400;

    protected int $quality = 82;

    /**
     * The disk the images are stored on.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * The directory on the disk the images are stored in.
     */
    public function path(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    /**
     * Scale stored images down to this longest edge.
     */
    public function maxEdge(int $pixels): static
    {
        $this->maxEdge = $pixels;

        return $this;
    }

    /**
     * Scale thumbnails down to this longest edge.
     */
    public function thumbnailEdge(int $pixels): static
    {
        $this->thumbnailEdge = $pixels;

        return $this;
    }

    /**
     * The images held in a stored or submitted value, in order.
     *
     * Only the keys a gallery keeps are kept, and an image is dropped unless
     * both of its files sit in the given directory, so a submitted list can
     * never point the page, or a later clean-up, at any other file.
     *
     * @return list<array{image: string, thumb: string, width: ?int, height: ?int, alt: ?string, caption: ?string}>
     */
    public static function images(mixed $value, ?string $directory = null): array
    {
        $value = is_string($value) ? json_decode($value, true) : json_decode((string) json_encode($value), true);

        if (! is_array($value)) {
            return [];
        }

        $images = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $image = $item['image'] ?? null;
            $thumb = $item['thumb'] ?? null;

            if (! static::isStoredPath($image, $directory)) {
                continue;
            }

            $images[] = [
                'image' => $image,
                'thumb' => static::isStoredPath($thumb, $directory) ? $thumb : $image,
                'width' => static::dimension($item['width'] ?? null),
                'height' => static::dimension($item['height'] ?? null),
                'alt' => static::text($item['alt'] ?? null),
                'caption' => static::text($item['caption'] ?? null),
            ];
        }

        return $images;
    }

    /**
     * The upload settings carried by a token from this field.
     *
     * @return array{disk: string, directory: string, maxEdge: int, thumbnailEdge: int, quality: int}|null
     */
    public static function settingsFromToken(string $token): ?array
    {
        try {
            $settings = Crypt::decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($settings) || array_keys($settings) !== ['disk', 'directory', 'maxEdge', 'thumbnailEdge', 'quality']) {
            return null;
        }

        return $settings;
    }

    /**
     * Resolve the given attribute from the given resource.
     */
    protected function resolveAttribute($resource, string $attribute): mixed
    {
        return static::images(parent::resolveAttribute($resource, $attribute), $this->directory);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  Model|Fluent  $model
     */
    protected function fillAttributeFromRequest(NovaRequest $request, string $requestAttribute, object $model, string $attribute): void
    {
        if ($request->exists($requestAttribute)) {
            $model->{$attribute} = static::images($request->input($requestAttribute), $this->directory);
        }
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * The upload settings travel to the browser encrypted, so the upload route
     * stores on the disk and in the directory this field names and nowhere else.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'uploadUrl' => '/nova-vendor/gallery/upload',
            'uploadToken' => Crypt::encrypt([
                'disk' => $this->disk,
                'directory' => $this->directory,
                'maxEdge' => $this->maxEdge,
                'thumbnailEdge' => $this->thumbnailEdge,
                'quality' => $this->quality,
            ]),
            'baseUrl' => rtrim(Storage::disk($this->disk)->url(''), '/'),
            'maxEdge' => $this->maxEdge,
        ]);
    }

    protected static function isStoredPath(mixed $path, ?string $directory): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_contains($path, '..')
            && ($directory === null || str_starts_with($path, $directory.'/'));
    }

    protected static function dimension(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected static function text(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, 1000);
    }
}
