<?php

use App\Models\Feature;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
 * The Unsaved changes line at the head of the feature form keeps what is
 * typed in the browser until it is saved (office-unsaved-work.js). Asking
 * for kept changes back parks them for the form's next load, which opens
 * with them in it and saves nothing until Update is pressed.
 */

beforeEach(function () {
    $this->staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
});

/**
 * Load an Office form as Nova's own page does: a plain GET that asks for
 * JSON. Not getJson(), which sends a JSON body too, and Nova copies the
 * request of a form it loads to fill in each field's value, a copy that
 * shares a JSON body's input with the original and so writes every value
 * back into it, the body's included, where the body cannot be read.
 */
function loadOfficeForm(string $uri): TestResponse
{
    return test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get($uri);
}

/**
 * A field of the feature form, by its attribute, as the Office draws it.
 *
 * @return array<string, mixed>|null
 */
function officeFormField(TestResponse $form, string $attribute): ?array
{
    return collect($form->json('fields'))->firstWhere('attribute', $attribute);
}

/**
 * Kept changes as the browser sends them back: the fields the feature form
 * would have sent on Update, body and all, in its own order.
 *
 * @return list<array{0: string, 1: string}>
 */
function keptFeatureFields(string $title, string $paragraph): array
{
    return [
        ['status', 'draft'],
        ['title', $title],
        ['___nova_flexible_content_fields', '["body"]'],
        ['body', json_encode([
            ['layout' => 'copy', 'key' => 'c0py1', 'attributes' => ['c0py1__text' => "<p>{$paragraph}</p>"]],
        ])],
    ];
}

test('the feature form carries the line that keeps unsaved changes', function () {
    $feature = Feature::factory()->create();

    $this->actingAs($this->staff);

    $form = loadOfficeForm("/nova-api/features/{$feature->id}/update-fields?editing=true&editMode=update");

    $form->assertOk();
    expect(officeFormField($form, 'unsaved_changes'))
        ->component->toBe('gop-unsaved-changes')
        ->restoreUrl->toBe("/nova-vendor/unsaved-drafts/features/{$feature->id}")
        ->lastSavedAt->toBe($feature->updated_at->toIso8601String());
});

test('the new feature form keeps its changes too', function () {
    $this->actingAs($this->staff);

    $form = loadOfficeForm('/nova-api/features/creation-fields?editing=true&editMode=create');

    $form->assertOk();
    expect(officeFormField($form, 'unsaved_changes'))
        ->restoreUrl->toBe('/nova-vendor/unsaved-drafts/features')
        ->lastSavedAt->toBeNull();
});

test('the office script draws the line the feature form asks for', function () {
    $script = file_get_contents(public_path('js/office-unsaved-work.js'));

    expect($script)->toContain("app.component('form-gop-unsaved-changes'");
});

test('kept changes come back into the feature form without saving it', function () {
    $feature = Feature::factory()->create(['title' => 'Grandmas House at the Exchange']);
    $original = $feature->body;
    $this->actingAs($this->staff);

    $this->postJson(route('office.unsaved-drafts.restore', ['resource' => 'features', 'resourceId' => $feature->id]), [
        'fields' => keptFeatureFields('Grandmas House, half-written', 'Kept before the tab was closed.'),
    ])->assertNoContent();
    $form = loadOfficeForm("/nova-api/features/{$feature->id}/update-fields?editing=true&editMode=update");

    $form->assertOk();
    expect(officeFormField($form, 'title')['value'])->toBe('Grandmas House, half-written')
        ->and(json_encode(officeFormField($form, 'body')['value']))->toContain('Kept before the tab was closed.');
    expect($feature->fresh())
        ->title->toBe('Grandmas House at the Exchange')
        ->body->toBe($original);
});

test('kept changes come back once only', function () {
    $feature = Feature::factory()->create(['title' => 'Lice at the Louisiana']);
    $this->actingAs($this->staff);
    $this->postJson(route('office.unsaved-drafts.restore', ['resource' => 'features', 'resourceId' => $feature->id]), [
        'fields' => keptFeatureFields('Lice, half-written', 'Kept.'),
    ]);
    loadOfficeForm("/nova-api/features/{$feature->id}/update-fields?editing=true&editMode=update");

    $again = loadOfficeForm("/nova-api/features/{$feature->id}/update-fields?editing=true&editMode=update");

    expect(officeFormField($again, 'title')['value'])->toBe('Lice at the Louisiana');
});

test('kept changes for a new feature come back into the create form', function () {
    $this->actingAs($this->staff);

    $this->postJson(route('office.unsaved-drafts.restore', ['resource' => 'features']), [
        'fields' => keptFeatureFields('A feature never created', 'Lost to a sign-out.'),
    ])->assertNoContent();
    $form = loadOfficeForm('/nova-api/features/creation-fields?editing=true&editMode=create');

    expect(officeFormField($form, 'title')['value'])->toBe('A feature never created')
        ->and(json_encode(officeFormField($form, 'body')['value']))->toContain('Lost to a sign-out.');
    expect(Feature::count())->toBe(0);
});

test('kept changes are only taken back for forms that can put them back', function () {
    $this->actingAs($this->staff)
        ->postJson(route('office.unsaved-drafts.restore', ['resource' => 'listings', 'resourceId' => 1]), [
            'fields' => [['title', 'A listing']],
        ])
        ->assertNotFound();
});

test('kept changes are only taken from the office', function () {
    $feature = Feature::factory()->create();
    $url = route('office.unsaved-drafts.restore', ['resource' => 'features', 'resourceId' => $feature->id]);
    $fields = ['fields' => keptFeatureFields('Not the office', 'No.')];

    $this->postJson($url, $fields)->assertUnauthorized();

    $this->actingAs(User::factory()->writer()->create())
        ->postJson($url, $fields)
        ->assertForbidden();
});

test('kept changes come as the form\'s fields, by name and value', function () {
    $feature = Feature::factory()->create();
    $url = route('office.unsaved-drafts.restore', ['resource' => 'features', 'resourceId' => $feature->id]);
    $this->actingAs($this->staff);

    $this->postJson($url, [])->assertJsonValidationErrors('fields');
    $this->postJson($url, ['fields' => [['title']]])->assertJsonValidationErrors('fields.0');
});
