<?php

use App\Models\Listing;

/**
 * A listing with no picture of its own is shown one of the category
 * placeholders, which look enough like photographs to be taken for the
 * event's own. The stamp that says otherwise is held to the preview: it
 * is there to ask a promoter for a picture while they are still looking
 * at their own listing.
 *
 * The public pages carry no stamp at all. Most of what is on them came in
 * from the magazine without images, so a stamp there would mark hundreds
 * of listings nobody is in a position to fix.
 */
test('the stamp is shown in the preview and only there', function () {
    $markup = file_get_contents(resource_path('js/components/GOP/ListItem.vue'));

    expect($markup)->toContain('v-if="props.preview && !props.listing.thumbnail"');
});

test('the public listing pages carry no stamp', function (string $component) {
    $markup = file_get_contents(resource_path('js/components/GOP/'.$component));

    expect($markup)->not->toContain('PlaceholderFlash');
})->with(['ListingsIndex.vue', 'ListingsVenueIndex.vue']);

test('the stamp says what it is, at an angle, in red', function () {
    $markup = file_get_contents(resource_path('js/components/GOP/PlaceholderFlash.vue'));

    expect($markup)
        ->toContain('Image placeholder')
        ->toContain('-rotate-12')
        ->toContain('text-red-600');
});

/**
 * The stamp is hung off the thumbnail column, so a listing that has one
 * has to be distinguishable from a listing that does not by that column
 * alone - it is what the views read.
 */
test('a listing without a picture is a listing with no thumbnail', function () {
    expect(Listing::factory()->create(['thumbnail' => null])->thumbnail)->toBeNull();
});
