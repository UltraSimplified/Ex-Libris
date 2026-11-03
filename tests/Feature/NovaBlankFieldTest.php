<?php

use App\Models\Feature;
use App\Models\User;
use App\Support\FeatureStatus;
use Whitecube\NovaFlexibleContent\Http\FlexibleAttribute;

/*
 * Every box below writes a column that will not take a null, and Laravel
 * turns a blank box into exactly that on the way in. Without a rule of its
 * own the save travelled all the way to the database and failed there,
 * which the Office could only show as an error page rather than as a word
 * under the field.
 */
test('the office turns a blank field away with a validation error', function (string $resource, array $payload, string $field) {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->postJson('/nova-api/'.$resource, $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'a category with no name' => ['categories', ['name' => '', 'slug' => 'gigs'], 'name'],
    'a category with no slug' => ['categories', ['name' => 'Gigs', 'slug' => ''], 'slug'],
    'a tag with nothing in it' => ['tags', ['tag' => ''], 'tag'],
    'a venue with no name' => ['venues', ['name' => ''], 'name'],
    'a promoter with no name' => ['promoters', ['name' => ''], 'name'],
    'a stockist with no name' => ['stockists', ['name' => '', 'town' => 'Nottingham'], 'name'],
    'a feature with no colourway' => ['features', [
        'title' => 'A feature',
        'status' => FeatureStatus::Draft->value,
        'colourway' => '',
        'body' => json_encode([]),
        FlexibleAttribute::REGISTER => json_encode(['body']),
    ], 'colourway'],
]);

/*
 * These three selects each have a default on the column, so a save that
 * leaves the field out altogether still works and takes that default.
 * Only a blank pick, which arrives as a null, is turned away.
 */
test('the office still saves a record whose defaulted select was left out', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->postJson('/nova-api/features', [
            'title' => 'A feature',
            'status' => FeatureStatus::Draft->value,
            'body' => json_encode([]),
            FlexibleAttribute::REGISTER => json_encode(['body']),
        ])
        ->assertCreated();

    expect(Feature::sole()->colourway)->toBe('white');
});
