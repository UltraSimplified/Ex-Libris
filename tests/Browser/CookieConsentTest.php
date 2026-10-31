<?php

/*
 * A first visit opens the cookie banner, and accepting everything saves the
 * choice and puts the banner away.
 */
test('accepting all cookies closes the banner', function () {
    visit('/')
        ->assertVisible('#accept-all-cookies')
        ->click('#accept-all-cookies')
        ->wait(1)
        ->assertScript("document.getElementById('scify-cookies-consent').style.display", 'none')
        ->assertMissing('#accept-all-cookies')
        ->assertScript("document.cookie.includes('cookies_consent=')", true)
        ->assertNoJavaScriptErrors();
});

/*
 * There is no floating cookie icon; the footer's Cookie consent link is the
 * way back to the banner once a choice has been made.
 */
test('the footer link brings the banner back', function () {
    visit('/')
        ->click('#accept-all-cookies')
        ->wait(1)
        ->assertMissing('#scify-cookie-consent-floating-button')
        ->click('.site-footer button:has-text("Cookie consent.")')
        ->assertScript("document.getElementById('scify-cookies-consent').style.display", 'block')
        ->assertVisible('#accept-all-cookies')
        ->assertNoJavaScriptErrors();
});
