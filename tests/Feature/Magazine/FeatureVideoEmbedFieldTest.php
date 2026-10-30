<?php

use App\Models\Feature;
use App\Models\User;

const VIDEO_EMBED_CODE = '<iframe width="560" height="315" src="https://www.youtube.com/embed/5k69qXrK7X8" title="YouTube video player" allowfullscreen></iframe>';

/**
 * The fields of the Video layout in a feature's body, as the Nova editor is
 * sent them when the feature is opened for editing.
 *
 * @return list<array<string, mixed>>
 */
function videoLayoutFieldsSentToTheEditor(Feature $feature): array
{
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    // Asked for as the editor asks, a plain GET for JSON.
    $fields = test()->actingAs($staff)
        ->get('/nova-api/features/'.$feature->id.'/update-fields?editing=true&editMode=update', [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->json('fields');

    $video = collect(collect($fields)->firstWhere('attribute', 'body')['value'])->firstWhere('layout', 'video');

    return $video['attributes'];
}

test('the video layout edits its embed code in the field that previews it', function () {
    $feature = Feature::factory()->create(['body' => json_encode([
        ['layout' => 'video', 'key' => 'cVideo0000000001', 'attributes' => ['video' => VIDEO_EMBED_CODE]],
    ])]);

    [$field] = videoLayoutFieldsSentToTheEditor($feature);

    expect($field['component'])->toBe('video-embed')
        ->and($field['attribute'])->toEndWith('video')
        ->and($field['value'])->toBe(VIDEO_EMBED_CODE)
        ->and($field['helpText'])->toContain('YouTube or Vimeo');
});

test('a video layout with no embed code yet is sent to the editor empty', function () {
    $feature = Feature::factory()->create(['body' => json_encode([
        ['layout' => 'video', 'key' => 'cVideo0000000001', 'attributes' => ['video' => null]],
    ])]);

    [$field] = videoLayoutFieldsSentToTheEditor($feature);

    expect($field['component'])->toBe('video-embed')
        ->and($field['value'])->toBeNull();
});
