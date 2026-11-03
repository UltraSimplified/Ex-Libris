<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

/**
 * The migration that marks accounts from before verification was required.
 */
function verifiedBackfill(): Migration
{
    return require database_path('migrations/2026_11_03_210100_mark_existing_accounts_as_verified.php');
}

test('accounts from before verification was required are not locked out', function () {
    $existing = User::factory()->unverified()->create();
    $verifiedAt = now()->subYear()->startOfSecond();
    $alreadyVerified = User::factory()->create(['email_verified_at' => $verifiedAt]);
    $noAddress = User::factory()->unverified()->create(['email' => null]);

    verifiedBackfill()->up();

    expect($existing->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($alreadyVerified->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue()
        ->and($noAddress->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($existing->fresh())->get(route('addresses.edit'))->assertOk();
});

test('rolling back the migration leaves accounts that verified for themselves alone', function () {
    $existing = User::factory()->unverified()->create();
    $alreadyVerified = User::factory()->create();

    verifiedBackfill()->up();
    verifiedBackfill()->down();

    expect($existing->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($alreadyVerified->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('an account made in the Office with an address is ready to use', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->postJson('/nova-api/users', [
            'name' => 'Vera Venue',
            'email' => 'vera@example.com',
            'password' => 'A-long-Passw0rd-for-Vera!',
            'is_writer' => false,
            'is_promoter' => false,
            'is_venue_mgr' => true,
        ])
        ->assertCreated();

    expect(User::where('email', 'vera@example.com')->sole()->hasVerifiedEmail())->toBeTrue();
});

test('the Office vouches for an address it changes, but not for an edit that leaves it alone', function () {
    $office = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $renamed = User::factory()->unverified()->create();
    $readdressed = User::factory()->unverified()->create();

    foreach ([[$renamed, $renamed->email], [$readdressed, 'staff-typed@example.com']] as [$user, $email]) {
        $this->actingAs($office)
            ->putJson("/nova-api/users/{$user->id}", [
                'name' => 'Edited In The Office',
                'email' => $email,
                'password' => '',
                'is_writer' => false,
                'is_promoter' => false,
                'is_venue_mgr' => false,
            ])
            ->assertOk();
    }

    expect($renamed->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($readdressed->fresh()->hasVerifiedEmail())->toBeTrue();
});
