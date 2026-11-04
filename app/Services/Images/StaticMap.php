<?php

namespace App\Services\Images;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * A street map with a pin on it, as a picture.
 *
 * The order emails cannot run Leaflet the way a venue's page does, so the
 * map they show has to be an ordinary image at an ordinary URL. This builds
 * one: the OpenStreetMap tiles around the point are fetched, composited,
 * pinned in the site's hi-vis and stored on the DigitalOcean Spaces disk,
 * then served through the CDN like any other picture.
 *
 * It is built once per place and kept. OpenStreetMap's tile policy asks
 * that tiles are not fetched systematically, so nothing here fetches them
 * per reader or per email - the file made for the first order going to a
 * postcode is the file every later one points at, and the collection
 * address is a single file for good.
 *
 * A PNG rather than a JPEG, because map labels this small turn to mush
 * under JPEG, and the image is linked rather than attached so its weight
 * costs the email nothing.
 */
final class StaticMap
{
    /**
     * OpenStreetMap's own tile servers, which ask for the credit in return.
     * Readers are shown it beside the picture, where there is room for a
     * link, rather than printed into the corner of the image.
     */
    public const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    public const CREDIT = 'OpenStreetMap contributors';

    private const TILE = 256;

    /**
     * Close enough to name the street without being so close that a
     * postcode-centre pin looks like a pin on the door.
     */
    private const ZOOM = 16;

    private const WIDTH = 600;

    private const HEIGHT = 280;

    private const DISK = 'do';

    private const PATH = 'maps';

    /**
     * How long one tile has to arrive. A dozen of them are fetched in turn,
     * so this is kept short: a tile server having a bad morning should cost
     * a queued email a moment, not a minute.
     */
    private const TIMEOUT = 4;

    public function width(): int
    {
        return self::WIDTH;
    }

    public function height(): int
    {
        return self::HEIGHT;
    }

    /**
     * Where the map of a place is, drawing it if it is not there yet. Null
     * when there is no place to draw, or the tiles could not be had.
     */
    public function url(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $path = sprintf(
            '%s/%d-%s-%s-%dx%d.png',
            self::PATH,
            self::ZOOM,
            number_format($latitude, 5, '.', ''),
            number_format($longitude, 5, '.', ''),
            self::WIDTH,
            self::HEIGHT,
        );

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        $drawn = $this->draw($latitude, $longitude);

        if ($drawn === null) {
            return null;
        }

        $disk->put($path, $drawn);

        return $disk->url($path);
    }

    /**
     * The composited map as PNG bytes, or null if it could not be drawn.
     */
    private function draw(float $latitude, float $longitude): ?string
    {
        [$centreX, $centreY] = $this->worldPixel($latitude, $longitude);

        $left = (int) round($centreX - self::WIDTH / 2);
        $top = (int) round($centreY - self::HEIGHT / 2);

        $tiles = $this->tiles($left, $top);

        if ($tiles === null) {
            return null;
        }

        try {
            return (string) $this->compose($tiles, $left, $top)
                ->encodeUsingFormat(Format::PNG);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Lay the tiles out on a grid, cut the window we want out of it, and
     * put the pin in the middle.
     *
     * The grid is built whole and cropped afterwards rather than each tile
     * being placed at an offset that may be negative, so every tile lands
     * inside the canvas it is placed on.
     *
     * @param  list<array{column: int, row: int, body: string}>  $tiles
     */
    private function compose(array $tiles, int $left, int $top): ImageInterface
    {
        $columns = array_map(fn (array $tile): int => $tile['column'], $tiles);
        $rows = array_map(fn (array $tile): int => $tile['row'], $tiles);
        $firstColumn = min($columns);
        $firstRow = min($rows);

        $grid = Image::createImage(
            (max($columns) - $firstColumn + 1) * self::TILE,
            (max($rows) - $firstRow + 1) * self::TILE,
        );

        foreach ($tiles as $tile) {
            $grid->insert(
                $tile['body'],
                ($tile['column'] - $firstColumn) * self::TILE,
                ($tile['row'] - $firstRow) * self::TILE,
            );
        }

        $grid->crop(
            self::WIDTH,
            self::HEIGHT,
            $left - $firstColumn * self::TILE,
            $top - $firstRow * self::TILE,
        );

        return $this->pin($grid);
    }

    /**
     * The pin, drawn rather than pasted so there is no marker picture to
     * keep, and in the same hi-vis as the pin on a venue's map.
     */
    private function pin(ImageInterface $map): ImageInterface
    {
        return $map->drawCircle(function (CircleFactory $circle): void {
            $circle->at((int) (self::WIDTH / 2), (int) (self::HEIGHT / 2));
            $circle->radius(11);
            $circle->background('#fced3c');
            $circle->border('#343434', 3);
        });
    }

    /**
     * Fetch every tile the window overlaps, or null if any of them fails:
     * a map with a hole in it is worse than no map.
     *
     * @return list<array{column: int, row: int, body: string}>|null
     */
    private function tiles(int $left, int $top): ?array
    {
        $span = 2 ** self::ZOOM;
        $fetched = [];

        foreach ($this->grid($left, $top) as [$column, $row]) {
            if ($row < 0 || $row >= $span) {
                continue;
            }

            $body = $this->tile(($column % $span + $span) % $span, $row);

            if ($body === null) {
                return null;
            }

            $fetched[] = ['column' => $column, 'row' => $row, 'body' => $body];
        }

        return $fetched === [] ? null : $fetched;
    }

    /**
     * The tile columns and rows the window overlaps.
     *
     * @return list<array{int, int}>
     */
    private function grid(int $left, int $top): array
    {
        $firstColumn = (int) floor($left / self::TILE);
        $lastColumn = (int) floor(($left + self::WIDTH - 1) / self::TILE);
        $firstRow = (int) floor($top / self::TILE);
        $lastRow = (int) floor(($top + self::HEIGHT - 1) / self::TILE);

        $grid = [];

        for ($column = $firstColumn; $column <= $lastColumn; $column++) {
            for ($row = $firstRow; $row <= $lastRow; $row++) {
                $grid[] = [$column, $row];
            }
        }

        return $grid;
    }

    /**
     * One tile's bytes, or null if the tile server would not give it to us.
     */
    private function tile(int $column, int $row): ?string
    {
        $url = str_replace(
            ['{z}', '{x}', '{y}'],
            [(string) self::ZOOM, (string) $column, (string) $row],
            self::TILES,
        );

        try {
            $response = Http::withUserAgent($this->agent())->timeout(self::TIMEOUT)->get($url);
        } catch (ConnectionException $exception) {
            Log::warning('Could not reach the tile server to draw a map.', [
                'tile' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Who is asking, which OpenStreetMap's tile policy requires.
     */
    private function agent(): string
    {
        return config('app.name').' ('.config('app.url').')';
    }

    /**
     * Where a place is on the whole world laid out as one image at this
     * zoom, in pixels: the Web Mercator projection the tiles are cut from.
     *
     * @return array{float, float}
     */
    private function worldPixel(float $latitude, float $longitude): array
    {
        $size = self::TILE * 2 ** self::ZOOM;
        $radians = deg2rad($latitude);

        return [
            ($longitude + 180) / 360 * $size,
            (1 - log(tan($radians) + 1 / cos($radians)) / M_PI) / 2 * $size,
        ];
    }
}
