<?php

use App\Models\User;
use App\Nova\Guest;
use App\Nova\PromoterAccount;
use App\Nova\User as NovaUser;
use App\Nova\VenueManager;
use App\Nova\Writer;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;

/**
 * The names an Office list came back with, in its order.
 *
 * @return list<string>
 */
function officeListNames(string $uri): array
{
    return collect(test()->getJson($uri)->assertOk()->json('resources'))
        ->map(fn (array $resource): string => User::findOrFail($resource['id']['value'])->name)
        ->all();
}

/**
 * The roles a new account starts with on a list's Create form.
 *
 * @return array<string, bool>
 */
function rolesTickedOnCreate(string $uriKey): array
{
    return collect(test()->getJson("/nova-api/{$uriKey}/creation-fields")->assertOk()->json('fields'))
        ->filter(fn (array $field): bool => in_array($field['attribute'], User::ROLES, true))
        ->mapWithKeys(fn (array $field): array => [$field['attribute'] => (bool) $field['value']])
        ->sortKeys()
        ->all();
}

/**
 * The columns an Office list's table shows, by attribute.
 *
 * @return list<string>
 */
function officeListColumns(string $uriKey): array
{
    return collect(test()->getJson("/nova-api/{$uriKey}")->assertOk()->json('resources.0.fields'))
        ->pluck('attribute')
        ->all();
}

beforeEach(function () {
    User::factory()->create(['name' => 'Wren Writer', 'is_writer' => true]);
    User::factory()->create(['name' => 'Abe Author', 'is_writer' => true]);
    User::factory()->create(['name' => 'Val Venue', 'is_venue_mgr' => true]);
    User::factory()->create(['name' => 'Pat Promoter', 'is_promoter' => true]);
    User::factory()->create(['name' => 'Bea Both', 'is_writer' => true, 'is_venue_mgr' => true]);
    User::factory()->create(['name' => 'Gil Guest']);
    User::factory()->create(['name' => 'Gwen Guest']);

    $this->actingAs(User::factory()->create(['name' => 'Ola Office', 'email' => 'robin@ultrasimplified.com']));
});

test('each role list holds the accounts with that role, by name', function (string $uriKey, array $names) {
    expect(officeListNames("/nova-api/{$uriKey}"))->toBe($names);
})->with([
    'writers' => ['writers', ['Abe Author', 'Bea Both', 'Wren Writer']],
    'venue managers' => ['venue-managers', ['Bea Both', 'Val Venue']],
    'promoters' => ['promoter-accounts', ['Pat Promoter']],
]);

test('guests are the accounts with no role that are not the office, newest first', function () {
    expect(officeListNames('/nova-api/guests'))->toBe(['Gwen Guest', 'Gil Guest']);
});

test('the lists show the avatar, name and email alone, Writers the active tick and Users the roles as well', function (string $uriKey, array $columns) {
    expect(officeListColumns($uriKey))->toBe($columns);
})->with([
    'writers' => ['writers', ['avatar', 'name', 'email', 'is_active_writer']],
    'venue managers' => ['venue-managers', ['avatar', 'name', 'email']],
    'promoters' => ['promoter-accounts', ['avatar', 'name', 'email']],
    'guests' => ['guests', ['avatar', 'name', 'email']],
    'all users' => ['users', ['avatar', 'name', 'email', 'is_venue_mgr', 'is_promoter', 'is_writer']],
]);

test('a role list is never more than one page', function () {
    User::factory()->count(30)->create(['is_writer' => true]);

    expect(officeListNames('/nova-api/writers'))->toHaveCount(33);
});

test('guests come a page at a time', function () {
    User::factory()->count(30)->create();

    expect(officeListNames('/nova-api/guests'))->toHaveCount(25);
});

test('each role list makes a new account with its own role ticked', function (string $uriKey, array $ticked) {
    expect(rolesTickedOnCreate($uriKey))->toBe($ticked);
})->with([
    'writers' => ['writers', ['is_promoter' => false, 'is_venue_mgr' => false, 'is_writer' => true]],
    'venue managers' => ['venue-managers', ['is_promoter' => false, 'is_venue_mgr' => true, 'is_writer' => false]],
    'promoters' => ['promoter-accounts', ['is_promoter' => true, 'is_venue_mgr' => false, 'is_writer' => false]],
    'all users' => ['users', ['is_promoter' => false, 'is_venue_mgr' => false, 'is_writer' => false]],
]);

test('each role list offers to create one of its own', function (string $resource, string $button) {
    expect($resource::authorizedToCreate(Request::create('/')))->toBeTrue()
        ->and(__('Create :resource', ['resource' => $resource::singularLabel()]))->toBe($button);
})->with([
    'writers' => [Writer::class, 'Create Writer'],
    'venue managers' => [VenueManager::class, 'Create Venue manager'],
    'promoters' => [PromoterAccount::class, 'Create Promoter'],
]);

test('guests cannot be created in the Office', function () {
    expect(Guest::authorizedToCreate(Request::create('/')))->toBeFalse();

    $this->postJson('/nova-api/guests', ['name' => 'Made Up', 'email' => 'made.up@example.com'])->assertForbidden();

    expect(User::where('name', 'Made Up')->exists())->toBeFalse();
});

test('an account is still filed under Users, and found once in the search', function () {
    $results = collect($this->getJson('/nova-api/search?search=Wren')->assertOk()->json());

    expect(Nova::resourceForModel(User::class))->toBe(NovaUser::class)
        ->and($results->pluck('resourceName')->all())->toBe(['users']);
});
