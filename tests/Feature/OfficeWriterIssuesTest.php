<?php

use App\Models\Issue;
use App\Models\User;
use App\Nova\Issue as NovaIssue;
use App\Nova\User as NovaUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Nova\Fields\Tag;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The Issues field on a user in the Office.
 */
function officeIssuesField(User $user): Tag
{
    return collect((new NovaUser($user))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Tag && $field->attribute === 'issues');
}

/**
 * Saving a user's form in the Office with these issues in its Issues field.
 *
 * @param  list<Issue>  $issues
 */
function saveIssuesInTheOffice(User $user, array $issues): TestResponse
{
    return test()
        ->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->putJson("/nova-api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email ?? '',
            'password' => '',
            'is_writer' => $user->is_writer,
            'is_promoter' => $user->is_promoter,
            'is_venue_mgr' => $user->is_venue_mgr,
            'issues' => json_encode(array_map(fn (Issue $issue): array => ['value' => $issue->id], $issues)),
        ]);
}

test('a writer\'s issues are kept on their form in the Office', function () {
    $writer = User::factory()->writer()->create();
    [$kept, $added, $removed] = Issue::factory()->count(3)->create();
    $writer->issues()->attach([$kept->id, $removed->id]);

    expect(officeIssuesField($writer)->isShownOnUpdate(app(NovaRequest::class), $writer))->toBeTrue();

    saveIssuesInTheOffice($writer, [$kept, $added])->assertOk();

    expect($writer->issues()->reorder('issues.id')->pluck('issues.id')->all())->toBe([$kept->id, $added->id]);
});

/**
 * Issues are for the writers alone: nobody else is offered the field, and
 * saving their form with issues in it gives them none.
 */
test('only a writer is given issues', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $issue = Issue::factory()->create();

    expect(officeIssuesField($promoter)->authorizedToSee(app(NovaRequest::class)))->toBeFalse();

    saveIssuesInTheOffice($promoter, [$issue])->assertOk();

    expect($promoter->issues()->count())->toBe(0);
});

test('an issue is pictured by its cover, though it has no hero image', function () {
    Storage::fake('do');
    $issue = Issue::factory()->create(['cover_photo' => 'covers/63.webp', 'hero_image' => null]);

    expect((new NovaIssue($issue))->resolveAvatarUrl(app(NovaRequest::class)))
        ->toBe(Storage::disk('do')->url('covers/63.webp'));
});

test('an issue is picked by its number and month', function () {
    $issue = Issue::factory()->create(['issue_number' => 63, 'month_year' => 'March 2019']);

    expect((new NovaIssue($issue))->title())->toBe('Issue 63 — March 2019')
        ->and(NovaIssue::$search)->toContain('issue_number');
});

test('attaching the same issue twice keeps it once', function () {
    $writer = User::factory()->writer()->create();
    $issue = Issue::factory()->create();

    $writer->issues()->attach($issue);

    expect(fn () => $writer->issues()->attach($issue))->toThrow(QueryException::class)
        ->and($writer->issues()->count())->toBe(1);
});

test('a writer can be created with their issues in the Office', function () {
    $issue = Issue::factory()->create();

    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->postJson('/nova-api/users', [
            'name' => 'Nora Newcomer',
            'email' => '',
            'password' => '',
            'is_writer' => true,
            'is_promoter' => false,
            'is_venue_mgr' => false,
            'issues' => json_encode([['value' => $issue->id]]),
        ])
        ->assertCreated();

    $writer = User::where('name', 'Nora Newcomer')->sole();

    expect($writer->is_writer)->toBeTrue()
        ->and($writer->issues()->pluck('issues.id')->all())->toBe([$issue->id]);
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

test('a writer\'s Issues field lists theirs newest first by number', function () {
    ['writer' => $writer] = issuesOutOfOrder();

    $field = collect(test()->getJson("/nova-api/users/{$writer->id}/update-fields")->assertOk()->json('fields'))
        ->firstWhere('attribute', 'issues');

    expect(collect($field['value'])->pluck('display')->all())
        ->toBe(['Issue 110 — Month 110', 'Issue 63 — Month 63', 'Issue 53 — Month 53']);
});

/**
 * The picker offers every issue newest first, 63 too though the writer has
 * it: the form leaves out what is already picked.
 */
test('the issue picker offers issues newest first by number', function () {
    ['writer' => $writer, 'issues' => $issues] = issuesOutOfOrder();
    $writer->issues()->detach([$issues[110]->id, $issues[53]->id]);
    Issue::factory()->create(['issue_number' => 7, 'month_year' => 'Month 7']);
    Issue::factory()->create(['issue_number' => 120, 'month_year' => 'Month 120']);

    expect(officeIssueNumbers("/nova-api/users/{$writer->id}/attachable/issues?component=tag-field&viaRelationship=issues&search=&first=false"))
        ->toBe([120, 110, 63, 53, 7]);
});

test('sorting by a column still overrides the order', function () {
    issuesOutOfOrder();

    expect(officeIssueNumbers('/nova-api/issues?orderBy=issue_number&orderByDirection=asc'))->toBe([53, 63, 110]);
});
