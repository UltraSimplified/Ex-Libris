<?php

use App\Models\Listing;
use App\Models\User;

function describedListing(string $body): Listing
{
    return Listing::factory()->create([
        'title' => 'Village Fete',
        'slug' => 'village-fete',
        'start_date' => today()->addDay(),
        'body' => $body,
    ]);
}

/**
 * The link a reader is shown for an address in the description, or null:
 * all of its text, screen readers' included, and whether it carries an
 * icon. The description is drawn twice, for phones and for wider screens,
 * and only one of the two is ever shown.
 *
 * @return array{href: ?string, target: ?string, rel: ?string, text: string, icon: bool}|null
 */
function shownDescriptionLink(object $page, string $address): ?array
{
    $text = json_encode($address);

    return $page->script(<<<JS
        (() => {
            const link = [...document.querySelectorAll('a')]
                .find((a) => a.offsetParent !== null && a.textContent.startsWith({$text}));

            return link
                ? {
                    href: link.getAttribute('href'),
                    target: link.getAttribute('target'),
                    rel: link.getAttribute('rel'),
                    text: link.textContent,
                    icon: link.querySelector('svg[aria-hidden="true"]') !== null,
                }
                : null;
        })()
        JS);
}

test('an address in the description opens in a new tab, tagged as the listing\'s referral and marked as leaving the site', function () {
    $listing = describedListing('Tickets at https://example.com/fete?id=42&day=sat. Doors at seven.');

    $page = visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee('Doors at seven.');

    expect(shownDescriptionLink($page, 'https://example.com/fete?id=42&day=sat'))->toBe([
        'href' => "https://example.com/fete?id=42&day=sat&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}",
        'target' => '_blank',
        'rel' => 'nofollow noreferrer',
        'text' => 'https://example.com/fete?id=42&day=sat (opens in a new tab)',
        'icon' => true,
    ]);

    $page->assertNoJavaScriptErrors();
});

test('what is typed around and inside an address is shown as typed, never run', function () {
    $listing = describedListing("See https://example.com/it's<script>window.injected = true</script> or <b>this</b>");

    $page = visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee('<script>window.injected = true</script> or <b>this</b>');

    expect(shownDescriptionLink($page, "https://example.com/it's"))
        ->href->toBe("https://example.com/it's?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=listing-{$listing->id}")
        ->and($page->script('window.injected === undefined && ! document.querySelector("p b")'))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

test('an address on this site opens in place, with no mark of leaving it', function () {
    $address = rtrim((string) config('app.url'), '/').'/features';
    $listing = describedListing("Read our interview at {$address}");

    $page = visit(route('Listing', [$listing->id, $listing->slug], absolute: false));

    expect(shownDescriptionLink($page, $address))->toBe([
        'href' => $address,
        'target' => null,
        'rel' => null,
        'text' => $address,
        'icon' => false,
    ]);

    $page->assertNoJavaScriptErrors();
});

test('in a preview, an address on this site opens in a new tab, out of the frame', function () {
    $address = rtrim((string) config('app.url'), '/').'/features';
    $listing = describedListing("Read our interview at {$address}");
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    $page = visit(route('listings.preview', $listing, absolute: false));

    expect(shownDescriptionLink($page, $address))->target->toBe('_blank');

    $page->assertNoJavaScriptErrors();
});
