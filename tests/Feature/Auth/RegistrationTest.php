<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a new registration is emailed a link to verify the address', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo(User::where('email', 'test@example.com')->sole(), VerifyEmail::class);
});

test('an unverified account is held at the verification page, but can still correct its address', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});

/**
 * Post the registration form from one address. A password that does not
 * match still counts against the limit, and leaves the visitor a guest.
 *
 * @param  array<string, string>  $overrides
 */
function registerFrom(string $ip, array $overrides = []): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ]);
}

test('too many registrations from one address in a minute are refused on the form, and send nothing', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        registerFrom('203.0.113.1', ['password_confirmation' => 'does-not-match']);
    }

    registerFrom('203.0.113.1')
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('registrations spread out are still refused past the hourly limit', function () {
    foreach (range(1, 20) as $attempt) {
        registerFrom('203.0.113.1', ['password_confirmation' => 'does-not-match']);
        $this->travel(13)->seconds();
    }

    registerFrom('203.0.113.1')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('one address reaching the limit does not stop anybody else registering', function () {
    foreach (range(1, 6) as $attempt) {
        registerFrom('203.0.113.1', ['password_confirmation' => 'does-not-match']);
    }

    registerFrom('203.0.113.2')->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('somebody already logged in still cannot register', function () {
    $this->actingAs(User::factory()->create());

    registerFrom('203.0.113.1')->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
