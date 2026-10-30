<?php

use App\Models\Issue;
use App\Models\User;
use App\Nova\Issue as NovaIssue;
use App\Nova\User as NovaUser;
use Illuminate\Database\QueryException;
use Illuminate\Testing\TestResponse;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The Issues field on a user in the Office.
 */
function officeIssuesField(User $user): BelongsToMany
{
    return collect((new NovaUser($user))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof BelongsToMany && $field->attribute === 'issues');
}

/**
 * Attaching an issue to a user as the Office does it.
 */
function attachIssueInTheOffice(User $user, Issue $issue): TestResponse
{
    return test()
        ->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->postJson("/nova-api/users/{$user->id}/attach/issues", [
            'issues' => $issue->id,
            'issues_trashed' => false,
            'viaRelationship' => 'issues',
        ]);
}

test('a writer\'s issues can be kept in the Office', function () {
    $writer = User::factory()->writer()->create();
    $issue = Issue::factory()->create(['issue_number' => 63]);

    expect(officeIssuesField($writer)->authorizedToSee(app(NovaRequest::class)))->toBeTrue();

    attachIssueInTheOffice($writer, $issue)->assertOk();

    expect($writer->issues()->pluck('issues.id')->all())->toBe([$issue->id]);
});

/**
 * Issues are for the writers alone: nobody else is offered the field, and
 * the Office will not attach one to them by asking anyway.
 */
test('only a writer is given issues', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $issue = Issue::factory()->create();

    expect(officeIssuesField($promoter)->authorizedToSee(app(NovaRequest::class)))->toBeFalse();

    attachIssueInTheOffice($promoter, $issue)->assertUnprocessable()->assertJsonValidationErrors('issues');

    expect($promoter->issues()->count())->toBe(0);
});

test('an issue is picked by its number and month', function () {
    $issue = Issue::factory()->create(['issue_number' => 63, 'month_year' => 'March 2019']);

    expect(call_user_func(officeIssuesField(User::factory()->writer()->create())->display, new NovaIssue($issue)))
        ->toBe('Issue 63 — March 2019')
        ->and(NovaIssue::$search)->toContain('issue_number');
});

test('attaching the same issue twice keeps it once', function () {
    $writer = User::factory()->writer()->create();
    $issue = Issue::factory()->create();

    $writer->issues()->attach($issue);

    expect(fn () => $writer->issues()->attach($issue))->toThrow(QueryException::class)
        ->and($writer->issues()->count())->toBe(1);
});

/**
 * The Issues table belongs to a saved writer, so a new user's form has
 * nothing to ask for it - and must not trip over it.
 */
test('a writer can still be created in the Office', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->postJson('/nova-api/users', [
            'name' => 'Nora Newcomer',
            'email' => '',
            'password' => '',
            'is_writer' => true,
            'is_promoter' => false,
            'is_venue_mgr' => false,
        ])
        ->assertCreated();

    expect(User::where('name', 'Nora Newcomer')->sole()->is_writer)->toBeTrue();
});

/**
 * Issues 63, 110 and 53, made in that order so their ids do not already
 * run in number order, and a writer who has all three.
 *
 * @return array{writer: User, issues: array<int, Issue>}
 */
function issuesOutOfOrder(): array
{
    $issues = collect([63, 110, 53])
        ->mapWithKeys(fn (int $number): array => [$number => Issue::factory()->create(['issue_number' => $number, 'month_year' => "Month {$number}"])])
        ->all();

    $writer = User::factory()->writer()->create();
    $writer->issues()->attach(collect($issues)->pluck('id'));

    test()->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    return ['writer' => $writer, 'issues' => $issues];
}

/**
 * The issue numbers an Office list came back in.
 *
 * @return list<int>
 */
function officeIssueNumbers(string $uri): array
{
    return collect(test()->getJson($uri)->assertOk()->json('resources'))
        ->map(fn (array $resource): int => Issue::findOrFail($resource['id']['value'] ?? $resource['value'])->issue_number)
        ->all();
}

test('the Office lists issues newest first by number', function () {
    issuesOutOfOrder();

    expect(officeIssueNumbers('/nova-api/issues'))->toBe([110, 63, 53]);
});

test('a writer\'s Issues table runs newest first by number', function () {
    ['writer' => $writer] = issuesOutOfOrder();

    expect(officeIssueNumbers("/nova-api/issues?viaResource=users&viaResourceId={$writer->id}&viaRelationship=issues&relationshipType=belongsToMany"))
        ->toBe([110, 63, 53]);
});

/**
 * The picker leaves out what the writer already has - 63 here - and offers
 * the rest newest first.
 */
test('the issue picker offers issues newest first by number', function () {
    ['writer' => $writer, 'issues' => $issues] = issuesOutOfOrder();
    $writer->issues()->detach([$issues[110]->id, $issues[53]->id]);
    Issue::factory()->create(['issue_number' => 7, 'month_year' => 'Month 7']);
    Issue::factory()->create(['issue_number' => 120, 'month_year' => 'Month 120']);

    expect(officeIssueNumbers("/nova-api/users/{$writer->id}/attachable/issues?component=belongs-to-many-field&viaRelationship=issues&search=&first=false"))
        ->toBe([120, 110, 53, 7]);
});

test('sorting by a column still overrides the order', function () {
    issuesOutOfOrder();

    expect(officeIssueNumbers('/nova-api/issues?orderBy=issue_number&orderByDirection=asc'))->toBe([53, 63, 110]);
});
