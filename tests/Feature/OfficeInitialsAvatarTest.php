<?php

use App\Models\User;
use App\Nova\User as NovaUser;
use App\Support\InitialsAvatar;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * An account with no avatar is shown in the Office by its initials, in
 * white on a black block, rather than by whatever Gravatar has for its
 * email address.
 */
beforeEach(function () {
    Storage::fake('do');
});

/**
 * The Avatar field on a user in the Office, resolved for that user.
 */
function officeAvatarField(User $user): Avatar
{
    $field = collect((new NovaUser($user))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Avatar);

    $field->resolve($user);

    return $field;
}

/**
 * The SVG inside an initials avatar's data URL.
 */
function initialsSvg(string $url): string
{
    expect($url)->toStartWith('data:image/svg+xml;base64,');

    return base64_decode(substr($url, strlen('data:image/svg+xml;base64,')));
}

test('initials are the first letters of the first and last words', function (string $name, string $initials) {
    expect(InitialsAvatar::initials($name))->toBe($initials);
})->with([
    ['Robin Layfield', 'RL'],
    ['Nancy Trotter Landry', 'NL'],
    ['siân magill', 'SM'],
    ['  Siobáin   Drury ', 'SD'],
    ['Ásta Sigurðardóttir', 'ÁS'],
    ['Borderless', 'B'],
    ['', ''],
]);

test('the picture is the initials in white on black', function () {
    $svg = initialsSvg(InitialsAvatar::for('Robin Layfield'));

    expect($svg)
        ->toContain('fill="#000"')
        ->toContain('fill="#fff"')
        ->toContain('>RL</text>');
});

test('a name cannot break out of the picture', function () {
    $svg = initialsSvg(InitialsAvatar::for('<script> &'));

    expect($svg)
        ->toContain('>&lt;&amp;</text>')
        ->not->toContain('<script');
});

test('a user with no avatar is shown by their initials, not Gravatar', function () {
    $user = User::factory()->create(['name' => 'Paul Harper', 'avatar' => null]);

    $field = officeAvatarField($user);

    expect(initialsSvg($field->resolveThumbnailUrl()))->toContain('>PH</text>');
    expect(initialsSvg($field->resolvePreviewUrl()))->toContain('>PH</text>');
});

test('a user with an avatar is still shown by it', function () {
    $user = User::factory()->create(['avatar' => 'avatars/paul.webp']);

    expect(officeAvatarField($user)->resolveThumbnailUrl())
        ->toBe(Storage::disk('do')->url('avatars/paul.webp'));
});
