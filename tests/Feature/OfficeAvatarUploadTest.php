<?php

use App\Models\User;
use App\Nova\User as NovaUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A picture uploaded in the Office comes out the same as one uploaded in
 * Settings: a webp no bigger than 256 x 256.
 */
beforeEach(function () {
    Storage::fake('do');
});

/**
 * The Office's Avatar field for a user, and a request uploading the file.
 *
 * @return array{0: Avatar, 1: NovaRequest}
 */
function officeAvatarUpload(User $user, UploadedFile $file): array
{
    $request = NovaRequest::create('/', 'POST', files: ['avatar' => $file]);

    $field = collect((new NovaUser($user))->fields($request))
        ->first(fn ($field): bool => $field instanceof Avatar);

    $field->resolve($user);

    return [$field, $request];
}

test('a picture uploaded in the Office is stored as a 256 by 256 webp', function () {
    $user = User::factory()->create(['avatar' => null]);

    [$field, $request] = officeAvatarUpload($user, UploadedFile::fake()->image('face.png', 1600, 900));

    $field->fill($request, $user);

    expect($user->avatar)->toStartWith('avatars/')->toEndWith('.webp');

    $stored = Image::decodeBinary(Storage::disk('do')->get($user->avatar));

    expect($stored->width())->toBe(256)
        ->and($stored->height())->toBe(256);
});

test('a new picture in the Office removes the one it replaces', function () {
    Storage::disk('do')->put('avatars/old.webp', 'not really an image');

    $user = User::factory()->create(['avatar' => 'avatars/old.webp']);

    [$field, $request] = officeAvatarUpload($user, UploadedFile::fake()->image('face.jpg', 600, 600));

    $prune = $field->fill($request, $user);

    expect($prune)->toBeCallable();

    $prune();

    Storage::disk('do')->assertMissing('avatars/old.webp');
    Storage::disk('do')->assertExists($user->avatar);
});

test('the Office turns away a file that is not a picture', function () {
    $user = User::factory()->create(['avatar' => null]);

    $pdf = UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf');

    [$field, $request] = officeAvatarUpload($user, $pdf);

    expect(validator(['avatar' => $pdf], $field->getCreationRules($request))->fails())->toBeTrue();
});
