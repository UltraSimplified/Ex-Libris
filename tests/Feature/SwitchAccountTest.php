<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
});

/**
 * Switch from the signed-in account to the given one, as the Office's
 * "Switch to" does.
 */
function switchTo(User $account): TestResponse
{
    return test()->postJson('/nova-api/impersonate', [
        'resource' => 'users',
        'resourceId' => $account->id,
    ]);
}

test('staff can switch to a dashboard account and land on its dashboard', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($this->staff);

    switchTo($writer)->assertOk()->assertJsonPath('redirect', '/dashboard');

    $this->assertAuthenticatedAs($writer);
    expect(session('nova_impersonated_by'))->toBe($this->staff->id);
});

test('the office offers Switch to for dashboard accounts only', function () {
    $writer = User::factory()->writer()->create();
    $reader = User::factory()->create();
    $colleague = User::factory()->create(['email' => 'adam@goodonpaper.info']);

    $offered = collect($this->actingAs($this->staff)->getJson('/nova-api/users?perPage=50')->json('resources'))
        ->mapWithKeys(fn (array $row) => [$row['id']['value'] => $row['authorizedToImpersonate']]);

    expect($offered[$writer->id])->toBeTrue()
        ->and($offered[$reader->id])->toBeFalse()
        ->and($offered[$colleague->id])->toBeFalse()
        ->and($offered[$this->staff->id])->toBeFalse();
});

test('staff cannot switch to another member of staff', function () {
    $colleague = User::factory()->create(['email' => 'adam@goodonpaper.info']);

    $this->actingAs($this->staff);

    switchTo($colleague)->assertForbidden();
    $this->assertAuthenticatedAs($this->staff);
});

test('staff cannot switch to an account without a dashboard', function () {
    $this->actingAs($this->staff);

    switchTo(User::factory()->create())->assertForbidden();
    $this->assertAuthenticatedAs($this->staff);
});

test('nobody outside the office can switch accounts', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs(User::factory()->writer()->create());

    switchTo($writer)->assertForbidden();
});

test('a switched-to account cannot open the office', function () {
    $this->actingAs($this->staff);
    switchTo(User::factory()->writer()->create());

    $this->getJson('/nova-api/users')->assertForbidden();
});

test('every page says who switched to the account', function () {
    $this->actingAs($this->staff);
    switchTo(User::factory()->writer()->create());

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.switchedFrom', $this->staff->name));
});

test('nobody else is told of a switch', function () {
    $this->actingAs(User::factory()->writer()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.switchedFrom', null));
});

test('switching back returns staff to the account in the office', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($this->staff);
    switchTo($writer);

    $this->post(route('switch-back'))->assertRedirect("/office/resources/users/{$writer->id}");

    $this->assertAuthenticatedAs($this->staff);
    expect(session()->has('nova_impersonated_by'))->toBeFalse();
});

test('logging out of a switched-to account switches back instead', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($this->staff);
    switchTo($writer);

    $this->post(route('logout'))->assertRedirect("/office/resources/users/{$writer->id}");

    $this->assertAuthenticatedAs($this->staff);
});

test('logging out of your own account still logs out', function () {
    $this->actingAs(User::factory()->writer()->create());

    $this->post(route('logout'));

    $this->assertGuest();
});

test('switching back without having switched goes nowhere', function () {
    $writer = User::factory()->writer()->create();

    $this->actingAs($writer)
        ->post(route('switch-back'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($writer);
});

test('the owner-only settings are refused while switched', function (string $method, string $uri) {
    $this->actingAs($this->staff);
    switchTo(User::factory()->writer()->create());

    $this->call($method, $uri)->assertForbidden();
})->with([
    'password page' => ['GET', '/settings/password'],
    'password change' => ['PUT', '/settings/password'],
    'two-factor page' => ['GET', '/settings/two-factor'],
    'account deletion' => ['DELETE', '/settings/profile'],
]);

test('the owner-only settings stay open to the owner', function () {
    $this->actingAs(User::factory()->writer()->create())
        ->get('/settings/password')
        ->assertOk();
});
