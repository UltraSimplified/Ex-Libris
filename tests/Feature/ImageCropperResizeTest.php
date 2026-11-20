<?php

use App\Models\Product as ProductModel;
use App\Nova\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * A picture uploaded through an ImageCropper field with a size set is
 * scaled to fit inside it, keeping its shape, and never enlarged. The
 * product cover's size is the shop's, 1131 by 1600 (ShopPicture).
 *
 * The package once called Intervention Image 3 the version 2 way, which
 * forced every upload to the given width at its original height - tall
 * pictures came out squat, wide ones thin. The fix lives in the package
 * (TransformableImage::resizeImage), so this guards it against an update
 * that brings the old behaviour back.
 */
function storedUploadSize(int $width, int $height): array
{
    Storage::fake('do');

    $field = collect((new Product(new ProductModel))->fields(app(NovaRequest::class)))
        ->first(fn ($field) => $field instanceof ImageCropper && $field->attribute === 'image');
    $product = new ProductModel;
    $file = UploadedFile::fake()->image('print.jpg', $width, $height);

    $field->fill(NovaRequest::create('/nova-api/products', 'POST', [], [], ['image' => $file]), $product);

    [$storedWidth, $storedHeight] = getimagesizefromstring(Storage::disk('do')->get($product->image));

    return [$storedWidth, $storedHeight];
}

test('an upload is scaled to fit the size, keeping its shape', function (int $width, int $height, array $stored) {
    expect(storedUploadSize($width, $height))->toBe($stored);
})->with([
    'tall and smaller than the limit: left as it is' => [800, 1062, [800, 1062]],
    'wide and larger: scaled down to the width' => [3200, 2124, [1131, 751]],
    'tall and larger: scaled down to the height' => [2000, 3000, [1067, 1600]],
    'exactly the limit: left as it is' => [1131, 1600, [1131, 1600]],
]);
