<?php

use App\Models\Page;
use App\Models\User;

test('a page is reached by its slug alone', function () {
    Page::factory()->create([
        'title' => 'Sponsors',
        'slug' => 'sponsors',
        'body' => '<p>Thank you.</p>',
    ]);

    $this->get('/sponsors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Page')
            ->where('page.title', 'Sponsors')
            ->where('page.body', '<p>Thank you.</p>')
        );
});

test('a flexible body arrives as its layouts rather than as a string', function () {
    Page::factory()->create([
        'slug' => 'who-we-are',
        'body' => json_encode([
            ['layout' => 'copy', 'key' => 'c1', 'attributes' => ['text' => '<p>Hello.</p>']],
        ]),
    ]);

    $this->get('/who-we-are')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Page')
            ->where('page.body.0.layout', 'copy')
            ->where('page.body.0.attributes.text', '<p>Hello.</p>')
        );
});

test('a slug with no page behind it is not found', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('NotFound'));
});

test('the 404 is the site\'s own page even with debug on', function () {
    config(['app.debug' => true]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('NotFound'));
});

test('Nova keeps its own error handling', function () {
    // Nova sends a signed-out visitor to its login screen rather than
    // anywhere the site's 404 could reach.
    $this->get('/office/resources/nothing-here')
        ->assertRedirect();
});

test('the writers page keeps its own component and its list', function () {
    Page::factory()->create(['title' => 'Our writers', 'slug' => 'our-writers']);
    User::factory()->create(['name' => 'Sam Adams', 'is_writer' => true]);
    User::factory()->create(['name' => 'Jo Bloggs', 'is_writer' => true]);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Our-writers')
            ->where('writers.0.name', 'Jo Bloggs')
            ->where('writers.0.slug', 'jo-bloggs')
            ->where('writers.1.name', 'Sam Adams')
        );
});

test('a new page in Nova goes live without a route', function () {
    Page::factory()->create([
        'title' => 'Volunteer',
        'slug' => 'volunteer',
        'body' => '<p>Come and help.</p>',
    ]);

    $this->get('/volunteer')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.title', 'Volunteer'));
});

test('the catch-all does not swallow the routes registered before it', function () {
    // A page whose slug collides with a real route must not shadow it.
    Page::factory()->create(['title' => 'Shop', 'slug' => 'shop']);

    $this->get('/shop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Shop'));
});

test('nested paths are left to their own routes', function () {
    $this->get('/listings/today')->assertOk();
    $this->get('/magazine/features')->assertOk();
});

test('an uppercase or underscored slug is not treated as a page', function () {
    $this->get('/Who_We_Are')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('NotFound'));
});

/**
 * Advertise is an editable page in the Office like any other. It used to
 * be a component with nothing in it, reached at /magazine/advertise.
 */
test('the advertise page is editable in the Office rather than hard coded', function () {
    Page::factory()->create([
        'title' => 'Advertise',
        'slug' => 'advertise',
        'body' => '<p>Rates and deadlines.</p>',
    ]);

    $this->get('/advertise')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Page')
            ->where('page.slug', 'advertise')
            ->where('page.body', '<p>Rates and deadlines.</p>')
        );
});

test('the old magazine advertise path is gone', function () {
    Page::factory()->create(['title' => 'Advertise', 'slug' => 'advertise']);

    $this->get('/magazine/advertise')->assertNotFound();
});
