<?php

use App\Models\Issue;

test('the archive only links the covers of issues there is a file of', function () {
    $withFile = Issue::factory()->forYear(2018)->numbered(41)->create();
    Issue::factory()->forYear(2018)->numbered(42)->create(['pdf' => null]);

    visit('/magazine/archives/2018')
        ->assertSee('Issue 42')
        ->assertSee('Issue 41')
        ->assertCount('a[href^="/download/"]', 1)
        ->assertPresent("a[href=\"/download/{$withFile->id}\"]")
        ->assertNoJavaScriptErrors();
});
