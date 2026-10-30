<?php

use App\Models\User;
use App\Nova\Filters\UserRole;
use App\Nova\User as NovaUser;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The names the Role filter leaves on the Users page, for what is ticked.
 *
 * @param  array<string, bool>  $ticked
 * @return list<string>
 */
function usersWithRoles(array $ticked): array
{
    return (new UserRole)
        ->apply(NovaRequest::create('/nova-api/users'), User::query(), $ticked)
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

beforeEach(function () {
    User::factory()->create(['name' => 'Pat Promoter', 'is_promoter' => true]);
    User::factory()->create(['name' => 'Val Venue', 'is_venue_mgr' => true]);
    User::factory()->create(['name' => 'Wren Writer', 'is_writer' => true]);
    User::factory()->create(['name' => 'Bea Both', 'is_writer' => true, 'is_venue_mgr' => true]);
    User::factory()->create(['name' => 'Ray Reader']);
});

test('the users page has a Role filter', function () {
    $filters = collect((new NovaUser(new User))->filters(app(NovaRequest::class)));

    expect($filters->contains(fn ($filter): bool => $filter instanceof UserRole))->toBeTrue()
        ->and((new UserRole)->options(app(NovaRequest::class)))->toBe([
            'Writers' => 'is_writer',
            'Venue managers' => 'is_venue_mgr',
            'Promoters' => 'is_promoter',
        ]);
});

test('each role narrows the users to the accounts that hold it', function (string $role, array $names) {
    expect(usersWithRoles([$role => true]))->toBe($names);
})->with([
    'writers' => ['is_writer', ['Bea Both', 'Wren Writer']],
    'venue managers' => ['is_venue_mgr', ['Bea Both', 'Val Venue']],
    'promoters' => ['is_promoter', ['Pat Promoter']],
]);

test('ticking more than one role shows anybody who holds any of them', function () {
    expect(usersWithRoles(['is_writer' => true, 'is_promoter' => true, 'is_venue_mgr' => false]))
        ->toBe(['Bea Both', 'Pat Promoter', 'Wren Writer']);
});

test('ticking nothing shows everybody', function () {
    expect(usersWithRoles(['is_writer' => false, 'is_promoter' => false, 'is_venue_mgr' => false]))
        ->toHaveCount(5);
});
