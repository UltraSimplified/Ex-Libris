<?php

use App\Models\Feature;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use App\Nova\User as NovaUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The Writer field as Nova builds it on a feature.
 */
function novaWriterFieldOnFeature(): BelongsTo
{
    return collect((new NovaFeature(new Feature))->fields(NovaRequest::create('/nova-api/features', 'GET')))
        ->first(fn ($field): bool => $field instanceof BelongsTo && $field->attribute === 'user');
}

/**
 * Nova reads the relation, so a feature with no writer must leave the field
 * empty rather than showing the account the public pages credit it to.
 */
test('a feature with no writer leaves the writer field empty in nova', function () {
    User::factory()->create(['id' => Feature::GOOD_ON_PAPER_USER_ID, 'name' => 'Good on Paper']);

    $feature = Feature::factory()->unbylined()->create();

    $field = novaWriterFieldOnFeature();
    $field->resolve($feature);

    expect($field->belongsToId)->toBeNull()
        ->and($field->value)->toBeNull();
});

test('a feature with a writer shows that writer in nova', function () {
    $feature = Feature::factory()->create();

    $field = novaWriterFieldOnFeature();
    $field->resolve($feature);

    expect($field->belongsToId)->toBe($feature->user_id);
});

test('the writer field can create a new writer', function () {
    expect(novaWriterFieldOnFeature()->showCreateRelationButtonCallback)->toBeTrue();
});

test('a writer created from a feature is ticked as a writer', function () {
    $request = NovaRequest::create('/nova-api/users/creation-fields', 'GET', [
        'viaResource' => 'features',
        'editing' => 'true',
        'editMode' => 'create',
    ]);

    $field = collect((new NovaUser(new User))->fields($request))
        ->first(fn ($field): bool => $field instanceof Boolean && $field->attribute === 'is_writer');

    expect($field->resolveDefaultValue($request))->toBeTrue();
});

test('a user created from the users page is not made a writer', function () {
    $request = NovaRequest::create('/nova-api/users/creation-fields', 'GET', [
        'editing' => 'true',
        'editMode' => 'create',
    ]);

    $field = collect((new NovaUser(new User))->fields($request))
        ->first(fn ($field): bool => $field instanceof Boolean && $field->attribute === 'is_writer');

    expect($field->resolveDefaultValue($request))->toBeFalsy();
});

/**
 * The creation rules Nova applies to a user, for what the form submits.
 *
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function novaUserCreationRules(array $input): array
{
    $request = NovaRequest::create('/nova-api/users', 'POST', $input);

    return collect((new NovaUser(new User))->fields($request))
        // The relation tables - articles, issues - are not on the form.
        ->filter(fn ($field): bool => $field instanceof Field && ! $field instanceof ListableField)
        ->reduce(fn (array $rules, Field $field): array => array_merge($rules, $field->getCreationRules($request)), []);
}

/**
 * @param  array<string, mixed>  $input
 */
function novaUserPasses(array $input): bool
{
    return Validator::make($input, novaUserCreationRules($input))->passes();
}

test('a writer can be saved without an email address or password', function () {
    $writer = User::factory()->writer()->create(['email' => null, 'password' => null]);

    expect($writer->fresh()->email)->toBeNull()
        ->and($writer->fresh()->password)->toBeNull();
});

test('several writers can be saved without an email address', function () {
    User::factory()->writer()->create(['email' => null, 'password' => null]);
    User::factory()->writer()->create(['email' => null, 'password' => null]);

    expect(User::whereNull('email')->count())->toBe(2);
});

test('two logins still cannot share an email address', function () {
    User::factory()->writer()->create(['email' => 'shared@example.com']);
    User::factory()->writer()->create(['email' => 'shared@example.com']);
})->throws(QueryException::class);

test('a writer with no login passes the user rules without an email or password', function () {
    expect(novaUserPasses([
        'name' => 'Ada Writer',
        'email' => null,
        'password' => null,
        'is_writer' => true,
        'is_promoter' => false,
        'is_venue_mgr' => false,
    ]))->toBeTrue();
});

test('a writer who is given an email address must give a valid one', function () {
    expect(novaUserPasses([
        'name' => 'Ada Writer',
        'email' => 'not-an-address',
        'password' => null,
        'is_writer' => true,
    ]))->toBeFalse();
});

test('a writer who also promotes or manages a venue needs a login', function (string $role) {
    expect(novaUserPasses([
        'name' => 'Ada Writer',
        'email' => null,
        'password' => null,
        'is_writer' => true,
        $role => true,
    ]))->toBeFalse();
})->with(['is_promoter', 'is_venue_mgr']);

test('someone who is not a writer needs an email address and password', function () {
    expect(novaUserPasses([
        'name' => 'Venue Manager',
        'email' => null,
        'password' => null,
        'is_venue_mgr' => true,
    ]))->toBeFalse();
});
