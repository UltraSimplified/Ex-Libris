<?php

use App\Models\Page;
use App\Models\User;
use App\Nova\Fields\ActiveWriterToggle;
use App\Nova\User as NovaUser;
use App\Nova\Writer as NovaWriter;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A writer is either active, and listed on Our Writers, or an alumnus,
 * kept for their bylines and their own page but off the masthead. The
 * office changes it from a tickbox on the writer's page in the Office.
 */
beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The Active writer tickbox on a user's page in the Office, if it is there.
 */
function activeWriterToggle(User $user): ?ActiveWriterToggle
{
    $request = app(NovaRequest::class);

    return collect((new NovaUser($user))->fields($request))
        ->first(fn ($field): bool => $field instanceof ActiveWriterToggle && $field->isShownOnDetail($request, $user));
}

test('every writer starts active', function () {
    $writer = User::factory()->writer()->create();

    expect($writer->fresh()->is_active_writer)->toBeTrue();
});

test('Our Writers lists the active writers and leaves the alumni off', function () {
    Page::factory()->create(['slug' => 'our-writers', 'title' => 'Our Writers']);
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->writer()->create(['name' => 'Judith Gunn', 'is_active_writer' => false]);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('writers', 1)
            ->where('writers.0.name', 'Paul Harper')
        );
});

/**
 * An alumnus's bylines still link to their page, so it stays up. Its
 * previous and next turn through the other alumni, and an active writer's
 * stay the names either side on Our Writers.
 */
test('an alumnus keeps their page and turns through the other alumni', function () {
    User::factory()->writer()->create(['name' => 'Adam Horovitz']);
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->writer()->create(['name' => 'Judith Gunn', 'is_active_writer' => false]);
    User::factory()->writer()->create(['name' => 'Siân Magill', 'is_active_writer' => false]);

    $this->get('/our-writers/judith-gunn')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writer.name', 'Judith Gunn')
            ->where('previous.name', 'Siân Magill')
            ->where('next.name', 'Siân Magill')
        );

    $this->get('/our-writers/adam-horovitz')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('previous.name', 'Paul Harper')
            ->where('next.name', 'Paul Harper')
        );
});

test('the office can make a writer an alumnus and back from the tickbox', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $writer = User::factory()->writer()->create();

    $this->actingAs($staff)
        ->putJson(route('writers.active', $writer), ['active' => false])
        ->assertOk()
        ->assertJsonPath('active', false);

    expect($writer->fresh()->is_active_writer)->toBeFalse();

    $this->actingAs($staff)
        ->putJson(route('writers.active', $writer), ['active' => true])
        ->assertOk()
        ->assertJsonPath('active', true);

    expect($writer->fresh()->is_active_writer)->toBeTrue();
});

test('somebody outside the office cannot change a writer', function () {
    $outsider = User::factory()->create(['email' => 'promoter@example.com']);
    $writer = User::factory()->writer()->create();

    $this->actingAs($outsider)
        ->putJson(route('writers.active', $writer), ['active' => false])
        ->assertForbidden();

    expect($writer->fresh()->is_active_writer)->toBeTrue();
});

test('only a writer can be made active or an alumnus', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $promoter = User::factory()->create(['is_promoter' => true]);

    $this->actingAs($staff)
        ->putJson(route('writers.active', $promoter), ['active' => false])
        ->assertNotFound();
});

test('a writer\'s page in the Office has the tickbox, pointed at the endpoint', function () {
    $writer = User::factory()->writer()->create(['is_active_writer' => false]);

    $field = activeWriterToggle($writer);
    $field->resolve($writer);

    $serialized = $field->jsonSerialize();

    expect($serialized['value'])->toBeFalse();
    expect($serialized['endpoint'])->toBe("/writers/{$writer->id}/active");
});

test('nobody but a writer is given the tickbox', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);

    expect(activeWriterToggle($promoter))->toBeNull();
});

test('the tickbox never writes to the writer itself', function () {
    $writer = User::factory()->writer()->create();

    (new ActiveWriterToggle('Active writer', 'is_active_writer'))->fill(app(NovaRequest::class), $writer);

    expect($writer->isDirty())->toBeFalse();
});

/**
 * The Writers list has a tick that saves when clicked, the same field as
 * the writer's page. Users, which lists everybody, goes without the column.
 */
test('the Writers list has the tick and Users does not', function () {
    $writer = User::factory()->writer()->create();
    $request = app(NovaRequest::class);

    $onIndex = fn (NovaUser $resource): bool => collect($resource->fields($request))
        ->contains(fn ($field): bool => $field instanceof ActiveWriterToggle && $field->isShownOnIndex($request, $writer));

    expect($onIndex(new NovaWriter($writer)))->toBeTrue();
    expect($onIndex(new NovaUser($writer)))->toBeFalse();
});
