<?php

use App\Models\Feature;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use App\Support\FeatureStatus;
use Illuminate\Testing\TestResponse;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Whitecube\NovaFlexibleContent\Http\FlexibleAttribute;

function officeStaff(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

/**
 * A published feature and a draft, both by the same writer under the same
 * tag, so every page that lists one would list the other.
 *
 * @return array{published: Feature, draft: Feature, writer: User}
 */
function publishedAndDraft(): array
{
    $writer = User::factory()->writer()->create(['name' => 'John Seagrave']);
    $published = Feature::factory()->for($writer)->create(['title' => 'Out Already']);
    $draft = Feature::factory()->draft()->for($writer)->create(['title' => 'Still Writing', 'tag_id' => $published->tag_id]);

    return ['published' => $published, 'draft' => $draft, 'writer' => $writer];
}

/**
 * The titles of the features a page was given, wherever on it they are.
 *
 * @return list<string>
 */
function featureTitlesOn(TestResponse $response): array
{
    $props = $response->viewData('page')['props'];

    return collect([
        $props['featured'] ?? [],
        $props['features'] ?? [],
        isset($props['latest']) ? [$props['latest']] : [],
        $props['more']['data'] ?? [],
    ])->flatten(1)->pluck('title')->all();
}

/**
 * Every page a feature is listed on, by the feature it lists.
 *
 * @return array<string, Closure(array{published: Feature, draft: Feature, writer: User}): string>
 */
function pagesListingFeatures(): array
{
    return [
        'the home page' => fn (): string => '/',
        'the features page' => fn (): string => '/magazine/features',
        'the tag page' => fn (array $features): string => '/magazine/features/'.$features['published']->tag->slug,
        'the writer page' => fn (): string => '/our-writers/john-seagrave',
    ];
}

test('a draft is left off every page for the public', function (Closure $url) {
    $features = publishedAndDraft();

    expect(featureTitlesOn($this->get($url($features))->assertOk()))
        ->toContain('Out Already')
        ->not->toContain('Still Writing');
})->with(pagesListingFeatures());

test('a draft is listed for the office, marked as a draft', function (Closure $url) {
    $features = publishedAndDraft();

    $response = $this->actingAs(officeStaff())->get($url($features))->assertOk();

    expect(featureTitlesOn($response))->toContain('Out Already', 'Still Writing');
})->with(pagesListingFeatures());

test('a draft\'s own page is not found by the public, nor by a writer', function () {
    ['draft' => $draft, 'writer' => $writer] = publishedAndDraft();

    $this->get("/magazine/features/{$draft->id}/{$draft->slug}")->assertNotFound();
    $this->actingAs($writer)->get("/magazine/features/{$draft->id}/{$draft->slug}")->assertNotFound();
});

test('the office can read a draft on its own page', function () {
    ['draft' => $draft] = publishedAndDraft();

    $this->actingAs(officeStaff())
        ->get("/magazine/features/{$draft->id}/{$draft->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.status', 'draft'));
});

/**
 * Saving a feature in the Office with these fields, as its form sends them.
 *
 * @param  array<string, mixed>  $fields
 */
function saveFeatureInTheOffice(array $fields, ?Feature $feature = null): TestResponse
{
    $request = [
        'body' => json_encode([]),
        FlexibleAttribute::REGISTER => json_encode(['body']),
        ...$fields,
    ];

    return $feature === null
        ? test()->postJson('/nova-api/features', $request)
        : test()->putJson("/nova-api/features/{$feature->id}", $request);
}

/**
 * The Status field on a feature's form in the Office.
 */
function officeStatusField(Feature $feature): Select
{
    return collect((new NovaFeature($feature))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Select && $field->attribute === 'status');
}

test('a new feature starts as a draft in the office', function () {
    $creating = NovaRequest::create('/nova-api/features/creation-fields', 'GET', ['editing' => 'true', 'editMode' => 'create']);

    expect(officeStatusField(new Feature)->resolveDefaultValue($creating))->toBe('draft');
});

test('a draft saves with nothing but its title', function () {
    $this->actingAs(officeStaff());

    saveFeatureInTheOffice(['status' => 'draft', 'title' => 'Half Written'])->assertCreated();

    $feature = Feature::where('title', 'Half Written')->sole();

    expect($feature->status)->toBe(FeatureStatus::Draft)
        ->and($feature->subject)->toBeNull()
        ->and($feature->tag_id)->toBeNull();
});

test('an unfinished feature cannot be published', function () {
    $this->actingAs(officeStaff());

    saveFeatureInTheOffice(['status' => 'published', 'title' => 'Half Written'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'published', 'tag', 'cover_photo', 'feature_image', 'excerpt']);

    expect(Feature::count())->toBe(0);
});

/**
 * An edit that leaves the pictures alone does not send them again, so the
 * ones the feature already has count towards publishing it.
 */
test('a finished draft is published, keeping its pictures', function () {
    $draft = Feature::factory()->draft()->create();
    $this->actingAs(officeStaff());

    saveFeatureInTheOffice([
        'status' => 'published',
        'title' => $draft->title,
        'subject' => $draft->subject,
        'published' => $draft->published->toDateString(),
        'tag' => $draft->tag_id,
        'excerpt' => $draft->excerpt,
    ], $draft)->assertOk();

    expect($draft->fresh()->status)->toBe(FeatureStatus::Published);
});

test('a published feature stays published when edited, and can be made a draft again', function () {
    $feature = Feature::factory()->create();
    $this->actingAs(officeStaff());

    $field = officeStatusField($feature);
    $field->resolve($feature);

    expect($field->value)->toBe('published');

    saveFeatureInTheOffice(['status' => 'draft', 'title' => $feature->title], $feature)->assertOk();

    expect($feature->fresh()->status)->toBe(FeatureStatus::Draft);
});
