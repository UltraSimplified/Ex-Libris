<?php

use App\Models\Page;

/**
 * A page body with an embed after a paragraph, as Nova stores it.
 */
function pageBodyWithEmbed(?string $code): string
{
    return json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Before the embed.</p>']],
        ['layout' => 'embed', 'key' => 'two', 'attributes' => ['code' => $code]],
    ]);
}

/**
 * Donorbox's code is a module script that defines a custom element, then
 * the element. A script added as markup is never run, so the widget was
 * left as an empty tag on /subscribe.
 */
test('the code in an embed layout is placed on the page and its scripts run', function () {
    Page::factory()->create([
        'slug' => 'embed-test',
        'body' => pageBodyWithEmbed(
            '<script type="module">customElements.define("test-widget", class extends HTMLElement { connectedCallback() { this.textContent = "Widget drawn"; } });</script>'
            .'<test-widget campaign="supporters"></test-widget>'
            .'<p id="classic">Classic script waiting</p>'
            .'<script>document.getElementById("classic").textContent = "Classic script ran";</script>'
        ),
    ]);

    visit('/embed-test')
        ->assertSee('Before the embed.')
        ->assertSee('Widget drawn')
        ->assertSee('Classic script ran')
        ->assertDontSee('<script')
        ->assertNoJavaScriptErrors();
});

test('an empty embed layout draws nothing', function () {
    Page::factory()->create(['slug' => 'embed-empty', 'body' => pageBodyWithEmbed(null)]);

    visit('/embed-empty')
        ->assertSee('Before the embed.')
        ->assertNoJavaScriptErrors();
});
