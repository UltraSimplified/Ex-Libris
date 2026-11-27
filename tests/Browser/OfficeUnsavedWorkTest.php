<?php

use App\Models\Feature;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Tests\Browser\Support\ParseMultipartForms;

/*
 * office-unsaved-work.js, driven in the Office. Features half-written there
 * were lost to switching to an account in another tab, which moves the
 * whole browser into that account: Nova met the refusal of its next call by
 * leaving the form for its 403 page.
 */

beforeEach(function () {
    app(Kernel::class)->prependMiddleware(ParseMultipartForms::class);

    $this->staff = User::factory()->create(['email' => 'alex@goodonpaper.info']);
    $this->promoter = User::factory()->create(['name' => 'Rough Trade Promotions']);
});

/**
 * Switch the browser to an account as the Office's Impersonate does, as if
 * from another tab: the page it is run on is left where it is.
 */
function switchInAnotherTab(User $account): string
{
    return <<<JS
        fetch('/nova-api/impersonate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ resource: 'users', resourceId: {$account->id} }),
        }).then((response) => response.status)
        JS;
}

/** Switch back, as the bar at the top of the site does, in another tab. */
function switchBackInAnotherTab(): string
{
    return <<<'JS'
        fetch('/switch-back', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]').content,
            },
        }).then((response) => response.status)
        JS;
}

/**
 * Sign out, and in again as the given account, in another tab: the page it
 * is run on keeps the token it had, which signing out makes stale.
 */
function signInInAnotherTab(User $account): string
{
    return <<<JS
        (async () => {
            const headers = async () => ({
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (await (await fetch('/account/signed-in', { headers: { Accept: 'application/json' } })).json()).csrfToken,
            })

            await fetch('/logout', { method: 'POST', headers: await headers() })

            const signedIn = await fetch('/login', {
                method: 'POST',
                headers: await headers(),
                body: JSON.stringify({ email: '{$account->email}', password: 'password' }),
            })

            return signedIn.ok
        })()
        JS;
}

/** One of the calls Nova makes on its own, as it does for notifications. */
const NOVA_OWN_CALL = "Nova.request().get('/nova-api/nova-notifications').then(() => 'let in', () => 'refused')";

test('a feature being written is held, not lost, while the browser is in another account', function () {
    $feature = Feature::factory()->draft()->create(['title' => 'Started in the Office']);
    $this->actingAs($this->staff);

    $page = visit("/office/resources/features/{$feature->id}/edit")
        ->assertSee('Your changes are kept in this browser')
        ->fill('[dusk="title"]', 'Finished after switching back');

    expect($page->script(switchInAnotherTab($this->promoter)))->toBe(200)
        ->and($page->script(NOVA_OWN_CALL))->toBe('refused');

    $page->assertSee('You are in Rough Trade Promotions’s account')
        ->click('Not now')
        ->click('Update & Continue Editing')
        ->assertSee('You are in Rough Trade Promotions’s account')
        ->assertPathIs("/office/resources/features/{$feature->id}/edit")
        ->assertValue('[dusk="title"]', 'Finished after switching back')
        ->click('Switch back to my account')
        ->assertSee('You are back in your own account')
        ->click('Update & Continue Editing')
        ->assertSee('The feature was updated!')
        ->assertNoJavaScriptErrors();

    expect($feature->fresh()->title)->toBe('Finished after switching back');
});

/*
 * Not now puts aside the notice for the switch it was pressed for, not for
 * the next: switching back in the other tab and in again, to the same
 * account, is another switch, though this tab never saw the switch back.
 */
test('a hold put aside comes back when the browser is switched again, even into the same account', function () {
    $feature = Feature::factory()->draft()->create(['title' => 'Started in the Office']);
    $this->actingAs($this->staff);

    $page = visit("/office/resources/features/{$feature->id}/edit")
        ->assertSee('Your changes are kept in this browser')
        ->fill('[dusk="title"]', 'Still being written');

    $page->script(switchInAnotherTab($this->promoter));
    $page->script(NOVA_OWN_CALL);
    $page->assertSee('You are in Rough Trade Promotions’s account')
        ->click('Not now');

    expect($page->script(switchBackInAnotherTab()))->toBe(200)
        ->and($page->script(switchInAnotherTab($this->promoter)))->toBe(200)
        ->and($page->script(NOVA_OWN_CALL))->toBe('refused');

    $page->assertSee('You are in Rough Trade Promotions’s account')
        ->assertPathIs("/office/resources/features/{$feature->id}/edit")
        ->assertValue('[dusk="title"]', 'Still being written')
        ->assertNoJavaScriptErrors();
});

/*
 * Signed out and in as somebody else, rather than switched: the Office
 * refuses the tab outright, and the sign-in back gives the session a new
 * token that the page has to pick up before it can save.
 */
test('a feature being written is held while the browser is signed in as somebody else', function () {
    $feature = Feature::factory()->draft()->create(['title' => 'Started in the Office']);
    $edit = "/office/resources/features/{$feature->id}/edit";
    $this->actingAs($this->staff);

    $page = visit($edit)
        ->assertSee('Your changes are kept in this browser')
        ->fill('[dusk="title"]', 'Saved once signed in again');

    expect($page->script(signInInAnotherTab($this->promoter)))->toBeTrue()
        ->and($page->script(NOVA_OWN_CALL))->toBe('refused');

    $page->assertSee('This browser is signed in as Rough Trade Promotions')
        ->assertPathIs($edit)
        ->assertValue('[dusk="title"]', 'Saved once signed in again');

    expect($page->script(signInInAnotherTab($this->staff)))->toBeTrue();

    $page->click('Carry on')
        ->assertSee('You are back in your own account')
        ->click('Update & Continue Editing')
        ->assertSee('The feature was updated!')
        ->assertNoJavaScriptErrors();

    expect($feature->fresh()->title)->toBe('Saved once signed in again');
});

test('changes to a feature left unsaved are offered back, and put back without saving', function () {
    $feature = Feature::factory()->draft()->create(['title' => 'As it was saved']);
    $edit = "/office/resources/features/{$feature->id}/edit";
    $this->actingAs($this->staff);

    $page = visit($edit)
        ->assertSee('Your changes are kept in this browser')
        ->fill('[dusk="title"]', 'As it was left');

    $page->navigate($edit)
        ->assertSee('You left changes here unsaved')
        ->assertValue('[dusk="title"]', 'As it was saved')
        ->click('Restore them')
        ->assertSee('are back. Check them over, then press Update to save them.')
        ->assertValue('[dusk="title"]', 'As it was left')
        ->assertNoJavaScriptErrors();

    expect($feature->fresh()->title)->toBe('As it was saved');
});

/*
 * The other tab last said so five minutes ago: a browser may wake a tab in
 * the background only once a minute or less to say it again.
 */
test('switching to an account warns first while another tab has unsaved changes', function () {
    $this->actingAs($this->staff);

    $page = visit("/office/resources/users/{$this->promoter->id}")
        ->assertSee('Rough Trade Promotions');

    $page->script(<<<'JS'
        localStorage.setItem('gop-open-forms', JSON.stringify({ elsewhere: { label: '“Started in the Office”', at: Date.now() - 5 * 60 * 1000 } }))
        window.confirm = (message) => { window.asked = message; return false }
        JS);

    $page->click("[dusk=\"{$this->promoter->id}-control-selector\"]")
        ->click("[dusk=\"{$this->promoter->id}-impersonate-button\"]")
        ->assertScript("window.asked.includes('You have unsaved changes to “Started in the Office” in another tab')", true)
        ->assertPathIs("/office/resources/users/{$this->promoter->id}")
        ->assertNoJavaScriptErrors();

    expect($page->script(NOVA_OWN_CALL))->toBe('let in');
});

test('switching to an account goes straight through when nothing is unsaved', function () {
    $this->actingAs($this->staff);

    visit("/office/resources/users/{$this->promoter->id}")
        ->assertSee('Rough Trade Promotions')
        ->click("[dusk=\"{$this->promoter->id}-control-selector\"]")
        ->click("[dusk=\"{$this->promoter->id}-impersonate-button\"]")
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});
