<?php

use App\Models\Issue;

test('an issue can be created', function () {
    $issue = Issue::factory()->create([
        'issue_number' => 42,
        'month_year' => 'March 2026',
        'tagline' => 'The one about paper',
    ]);

    expect($issue->exists)->toBeTrue();

    $this->assertDatabaseHas('issues', [
        'id' => $issue->id,
        'issue_number' => 42,
        'month_year' => 'March 2026',
        'tagline' => 'The one about paper',
    ]);
});

test('an issue can be created with only the optional columns left empty', function () {
    // Every column on issues is nullable, so a bare record is valid.
    $issue = Issue::factory()->create([
        'tagline' => null,
        'cover_photo' => null,
        'hero_image' => null,
        'alt_text' => null,
        'cover_artist' => null,
        'playlist' => null,
        'playlist_creator' => null,
        'pdf' => null,
    ]);

    expect($issue->exists)->toBeTrue()
        ->and($issue->fresh()->pdf)->toBeNull();
});

test('the forYear state formats month_year so the archive filter matches it', function () {
    $issue = Issue::factory()->forYear(2024, 3)->create();

    expect($issue->month_year)->toBe('March 2024');
});

test('issues are distinguishable by issue number', function () {
    Issue::factory()->numbered(11)->create();
    Issue::factory()->numbered(12)->create();

    expect(Issue::orderByDesc('issue_number')->pluck('issue_number')->all())
        ->toBe([12, 11]);
});
