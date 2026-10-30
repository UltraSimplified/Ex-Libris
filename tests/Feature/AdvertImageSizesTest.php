<?php

use App\Models\Advert as AdvertModel;
use App\Nova\Advert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Gif\Builder;
use Intervention\Gif\Decoder;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * The creatives go up at exactly the size they are shown at - 728×300 on
 * desktops and tablets, 400×300 on phones - and are kept as they arrive,
 * so an animated GIF stays animated.
 */
function advertImageField(string $name): Image
{
    return collect((new Advert(new AdvertModel))->fields(app(NovaRequest::class)))
        ->flatMap(fn ($field) => $field instanceof Panel ? $field->data : [$field])
        ->first(fn ($field) => $field instanceof Image && $field->name === $name);
}

function advertImageAccepts(string $name, UploadedFile $file): bool
{
    $field = advertImageField($name);
    $rules = $field->getCreationRules(NovaRequest::create('/nova-api/adverts', 'POST'));

    return Validator::make([$field->attribute => $file], $rules)->passes();
}

/**
 * A two-frame animated GIF, as GD cannot write one.
 */
function animatedGif(int $width, int $height): string
{
    $frame = function (int $red) use ($width, $height): string {
        $image = imagecreate($width, $height);
        imagecolorallocate($image, $red, 0, 0);
        ob_start();
        imagegif($image);

        return (string) ob_get_clean();
    };

    return Builder::canvas($width, $height)
        ->addFrame($frame(255), 0.5)
        ->addFrame($frame(0), 0.5)
        ->setLoops(0)
        ->encode();
}

test('the advert creatives are uploaded as they are, with no cropper', function (string $name) {
    expect(advertImageField($name))->not->toBeInstanceOf(ImageCropper::class);
})->with(['Desktop Image', 'Mobile Image']);

test('a creative at exactly its size is accepted as a GIF, PNG, WebP or JPEG', function (string $name, int $width, int $height, string $type) {
    expect(advertImageAccepts($name, UploadedFile::fake()->image("creative.{$type}", $width, $height)))->toBeTrue();
})->with([
    'desktop' => ['Desktop Image', 728, 300],
    'mobile' => ['Mobile Image', 400, 300],
])->with(['gif', 'png', 'webp', 'jpeg']);

test('a creative at any other size is turned away, larger ones included', function (string $name, int $width, int $height) {
    expect(advertImageAccepts($name, UploadedFile::fake()->image('creative.png', $width, $height)))->toBeFalse();
})->with([
    'desktop a pixel too wide' => ['Desktop Image', 729, 300],
    'desktop a pixel too short' => ['Desktop Image', 728, 299],
    'desktop at twice the size' => ['Desktop Image', 1456, 600],
    'desktop at the old size' => ['Desktop Image', 1920, 668],
    'desktop at the mobile size' => ['Desktop Image', 400, 300],
    'mobile a pixel too wide' => ['Mobile Image', 401, 300],
    'mobile at the old size' => ['Mobile Image', 1128, 1059],
    'mobile at the desktop size' => ['Mobile Image', 728, 300],
]);

test('a creative in any other format is turned away', function (string $name, int $width, int $height) {
    expect(advertImageAccepts($name, UploadedFile::fake()->image('creative.bmp', $width, $height)))->toBeFalse();
})->with([
    'desktop' => ['Desktop Image', 728, 300],
    'mobile' => ['Mobile Image', 400, 300],
]);

test('an animated GIF is accepted, and stored frame for frame as it was uploaded', function (string $name, int $width, int $height) {
    Storage::fake('do');
    $gif = animatedGif($width, $height);
    $file = UploadedFile::fake()->createWithContent('animated.gif', $gif);
    $field = advertImageField($name);
    $advert = new AdvertModel;

    expect(advertImageAccepts($name, $file))->toBeTrue();

    $field->fill(NovaRequest::create('/nova-api/adverts', 'POST', [], [], [$field->attribute => $file]), $advert);
    $stored = $advert->{$field->attribute};

    expect($stored)->toStartWith('sponsors/')->toEndWith('.gif')
        ->and(Storage::disk('do')->get($stored))->toBe($gif)
        ->and(Decoder::decode(Storage::disk('do')->get($stored))->frames())->toHaveCount(2);
})->with([
    'desktop' => ['Desktop Image', 728, 300],
    'mobile' => ['Mobile Image', 400, 300],
]);
