<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
test('a profile picture can be uploaded', function () {
    Storage::fake('do');

    $user = User::factory()->create(['avatar' => null]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('face.jpg', 900, 600),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $avatar = $user->refresh()->avatar;

    expect($avatar)->toStartWith('avatars/')->toEndWith('.webp');
    Storage::disk('do')->assertExists($avatar);

    $stored = Image::decodeBinary(Storage::disk('do')->get($avatar));

    expect($stored->width())->toBe(256)
        ->and($stored->height())->toBe(256);
});

test('a small profile picture is not enlarged', function () {
    Storage::fake('do');

    $user = User::factory()->create(['avatar' => null]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('face.jpg', 120, 80),
        ])
        ->assertSessionHasNoErrors();

    $stored = Image::decodeBinary(Storage::disk('do')->get($user->refresh()->avatar));

    expect($stored->width())->toBeLessThanOrEqual(120)
        ->and($stored->width())->toBe($stored->height());
});

test('a new profile picture replaces the one it succeeds', function () {
    Storage::fake('do');
    Storage::disk('do')->put('avatars/old.webp', 'not really an image');

    $user = User::factory()->create(['avatar' => 'avatars/old.webp']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->avatar)->not->toBe('avatars/old.webp');
    Storage::disk('do')->assertMissing('avatars/old.webp');
});

test('a profile picture can be removed', function () {
    Storage::fake('do');
    Storage::disk('do')->put('avatars/old.webp', 'not really an image');

    $user = User::factory()->create(['avatar' => 'avatars/old.webp']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => 'on',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->avatar)->toBeNull();
    Storage::disk('do')->assertMissing('avatars/old.webp');
});

test('the profile picture is left alone when the form sends no file', function () {
    Storage::fake('do');
    Storage::disk('do')->put('avatars/kept.webp', 'not really an image');

    $user = User::factory()->create(['avatar' => 'avatars/kept.webp']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->avatar)->toBe('avatars/kept.webp');
    Storage::disk('do')->assertExists('avatars/kept.webp');
});

test('a profile picture must be an image', function () {
    Storage::fake('do');

    $user = User::factory()->create(['avatar' => null]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar)->toBeNull();
});

/**
 * The byline used to be a record of its own, edited in the Office. It is
 * the profile now: the same picture, the same name, and a note under it
 * that the writer keeps themselves.
 */
test('a writer keeps their own byline', function () {
    $writer = User::factory()->writer()->create(['avatar' => 'avatars/mine.webp']);

    $this->actingAs($writer)
        ->patch(route('profile.update'), [
            'name' => $writer->name,
            'email' => $writer->email,
            'bio' => 'Writes about music, mostly from the back of the room.',
            'avatar_alt_text' => 'Sam outside the Prince Albert',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $writer->refresh();

    expect($writer->bio)->toBe('Writes about music, mostly from the back of the room.')
        ->and($writer->avatar_alt_text)->toBe('Sam outside the Prince Albert');
});

test('a byline is emptied rather than left blank', function () {
    $writer = User::factory()->writer()->create(['bio' => 'Something.']);

    $this->actingAs($writer)
        ->patch(route('profile.update'), [
            'name' => $writer->name,
            'email' => $writer->email,
            'bio' => '   ',
        ])
        ->assertSessionHasNoErrors();

    expect($writer->refresh()->bio)->toBeNull();
});

/**
 * The fields are only offered to a writer, so a byline posted by anybody
 * else is not saved - the form they were given never had it.
 */
test('somebody who does not write for us cannot give themselves a byline', function () {
    $user = User::factory()->create(['is_writer' => false]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => 'Award-winning correspondent.',
            'avatar_alt_text' => 'Not my picture',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->bio)->toBeNull()
        ->and($user->avatar_alt_text)->toBeNull();
});

test('a byline longer than we print is turned away', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($writer)
        ->patch(route('profile.update'), [
            'name' => $writer->name,
            'email' => $writer->email,
            'bio' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors('bio');
});

/**
 * A description of a picture that is no longer there describes nothing.
 */
test('removing the picture takes its description with it', function () {
    Storage::fake('do');

    $writer = User::factory()->writer()->create([
        'avatar' => 'avatars/mine.webp',
        'avatar_alt_text' => 'Sam outside the Prince Albert',
    ]);

    $this->actingAs($writer)
        ->patch(route('profile.update'), [
            'name' => $writer->name,
            'email' => $writer->email,
            'remove_avatar' => '1',
        ])
        ->assertSessionHasNoErrors();

    $writer->refresh();

    expect($writer->avatar)->toBeNull()
        ->and($writer->avatar_alt_text)->toBeNull();
});

test('the byline fields are offered to a writer and to nobody else', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($writer)->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.roles.is_writer', true));

    $this->actingAs(User::factory()->create(['is_writer' => false]))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.roles.is_writer', false));
});
