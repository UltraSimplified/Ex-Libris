<?php

use App\Models\Listing;
use App\Models\User;

function shareableListing(array $attributes = []): Listing
{
    return Listing::factory()->create([
        'title' => 'Village Fete',
        'slug' => 'village-fete',
        'start_date' => today()->addDay(),
        'event_link' => 'https://tickets.example.com/fete',
        'button_text' => 'Find out more',
        ...$attributes,
    ]);
}

/**
 * Whether the middle of something on the page is level with the middle of the
 * listing's button.
 */
function isLevelWithButton(string $selector): string
{
    return <<<JS
        (() => {
            const middle = (element) => {
                const box = element.getBoundingClientRect();

                return box.top + box.height / 2;
            };
            const button = document.querySelector('a[href*="tickets.example.com"] button');

            return Math.abs(middle(document.querySelector('{$selector}')) - middle(button)) < 1;
        })()
        JS;
}

test('the listing page passes an event on by WhatsApp or by its address', function () {
    $listing = shareableListing();

    $page = visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee('Share to WhatsApp');

    $message = $page->script(<<<'JS'
        new URL(document.querySelector('a[href^="https://wa.me/"]').href).searchParams.get('text')
        JS);

    expect($message)
        ->toContain('Village Fete at '.$listing->venue->name)
        ->toContain('/listings/'.$listing->id.'/village-fete');

    $page->script(<<<'JS'
        void (navigator.clipboard.writeText = (text) => {
            window.copiedText = text;

            return Promise.resolve();
        })
        JS);

    $page->click('Copy link')->assertSee('Copied');

    expect($page->script('window.copiedText'))->toEndWith('/listings/'.$listing->id.'/village-fete');

    $page->assertNoJavaScriptErrors();
});

test('the links sit level with the button', function (string $link) {
    $listing = shareableListing();

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->resize(1600, 1000)
        ->assertScript(isLevelWithButton($link), true)
        ->assertNoJavaScriptErrors();
})->with(['copy link' => 'button.underline', 'whatsapp' => 'a[href^="https://wa.me/"]']);

test('an event with no button can still be passed on', function () {
    $listing = shareableListing(['button_text' => null]);

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee('Copy link')
        ->assertSee('Share to WhatsApp')
        ->assertMissing('a[href*="tickets.example.com"]')
        ->assertNoJavaScriptErrors();
});

test('the links fit a phone without scrolling sideways', function () {
    $listing = shareableListing();

    $page = visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->on()->mobile()
        ->resize(320, 640)
        ->assertSee('Share to WhatsApp');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

/**
 * The preview is for reading the listing before it goes out, in a frame in
 * the Office, so there is nothing yet to pass on.
 */
test('the preview in the Office leaves the links off', function () {
    $listing = shareableListing();

    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    visit(route('listings.preview', $listing, absolute: false))
        ->assertSee('Find out more')
        ->assertDontSee('Copy link')
        ->assertDontSee('Share to WhatsApp')
        ->assertNoJavaScriptErrors();
});
