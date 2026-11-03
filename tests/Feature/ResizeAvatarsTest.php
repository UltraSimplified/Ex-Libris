<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

beforeEach(function () {
    Storage::fake('do');
    Storage::fake('local');
});

/**
 * An account whose avatar is a picture of the given size in the bucket.
 */
function userWithStoredAvatar(string $path, int $width, int $height): User
{
    Storage::disk('do')->put($path, UploadedFile::fake()->image(basename($path), $width, $height)->get());

    return User::factory()->create(['avatar' => $path]);
}

test('a dry run changes nothing', function () {
    $user = userWithStoredAvatar('avatars/big.png', 1080, 720);

    $this->artisan('avatars:resize')
        ->expectsOutputToContain('1 avatar(s) would be resized')
        ->assertSuccessful();

    expect($user->refresh()->avatar)->toBe('avatars/big.png');
    expect(Storage::disk('do')->allFiles())->toBe(['avatars/big.png']);
});

test('an oversized avatar is replaced by a 256 by 256 webp and the original kept', function () {
    $user = userWithStoredAvatar('avatars/big.png', 1080, 720);
    $lastEdited = $user->updated_at;

    $this->travel(1)->hour();

    $this->artisan('avatars:resize --commit')->assertSuccessful();

    $user->refresh();

    expect($user->avatar)->toStartWith('avatars/')->toEndWith('.webp')->not->toBe('avatars/big.png');
    expect($user->updated_at->equalTo($lastEdited))->toBeTrue();
    Storage::disk('do')->assertExists('avatars/big.png');

    $stored = Image::decodeBinary(Storage::disk('do')->get($user->avatar));

    expect($stored->width())->toBe(256)
        ->and($stored->height())->toBe(256);
});

test('each change is noted so that it can be undone', function () {
    $user = userWithStoredAvatar('avatars/big.png', 1080, 720);

    $this->artisan('avatars:resize --commit')->assertSuccessful();

    $note = json_decode(Storage::disk('local')->get(Storage::disk('local')->files()[0]), true);

    expect($note)->toBe(['user' => $user->id, 'from' => 'avatars/big.png', 'to' => $user->refresh()->avatar]);
});

test('an avatar that already fits is left alone', function () {
    $user = userWithStoredAvatar('avatars/small.jpg', 200, 200);

    $this->artisan('avatars:resize --commit')->assertSuccessful();

    expect($user->refresh()->avatar)->toBe('avatars/small.jpg');
});

test('running it twice changes nothing the second time', function () {
    $user = userWithStoredAvatar('avatars/big.png', 1080, 720);

    $this->artisan('avatars:resize --commit')->assertSuccessful();

    $resized = $user->refresh()->avatar;

    $this->artisan('avatars:resize --commit')
        ->expectsOutputToContain('Resized 0 avatar(s), 1 already fit')
        ->assertSuccessful();

    expect($user->refresh()->avatar)->toBe($resized);
});

test('the same original gives the same copy whichever account holds it', function () {
    $first = userWithStoredAvatar('avatars/big.png', 1080, 720);
    $second = User::factory()->create(['avatar' => 'avatars/big.png']);

    $this->artisan('avatars:resize --commit')->assertSuccessful();

    expect($first->refresh()->avatar)->toBe($second->refresh()->avatar);
    expect(Storage::disk('do')->allFiles())->toHaveCount(2);
});

test('a file that cannot be read is reported, left alone and does not stop the rest', function () {
    Storage::disk('do')->put('avatars/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $svg = User::factory()->create(['avatar' => 'avatars/logo.svg']);
    $missing = User::factory()->create(['avatar' => 'avatars/gone.jpg']);
    $big = userWithStoredAvatar('avatars/big.png', 1080, 720);

    $this->artisan('avatars:resize --commit')
        ->expectsOutputToContain('avatars/logo.svg')
        ->expectsOutputToContain('avatars/gone.jpg')
        ->assertSuccessful();

    expect($svg->refresh()->avatar)->toBe('avatars/logo.svg')
        ->and($missing->refresh()->avatar)->toBe('avatars/gone.jpg')
        ->and($big->refresh()->avatar)->toEndWith('.webp');
});
