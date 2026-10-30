<?php

use App\Models\User;

/**
 * What an account hears from us is a setting rather than a section of the
 * dashboard, so it sits with the rest of them under Profile. A stub:
 * nothing records a preference yet.
 */
test('guests are sent to sign in', function () {
    $this->get(route('notifications.edit'))->assertRedirect(route('login'));
});

test('an account can reach its notification settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Notifications'));
});

test('notifications are offered alongside the other settings', function () {
    $layout = file_get_contents(resource_path('js/layouts/settings/Layout.vue'));

    expect($layout)->toContain("title: 'Notifications'");
});

/**
 * And nowhere else. It was a band of its own in the sidebar before it was
 * a setting, and two ways in would be two things to keep in step.
 */
test('the sidebar does not offer it as well', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)->not->toContain("title: 'Notifications'");
});
