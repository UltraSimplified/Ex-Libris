<?php

use App\Http\Middleware\RequireHttps;

/**
 * The live site, the Dashboard and the Office are only served over HTTPS,
 * whether the certificate is on this server or on Cloudflare in front of it.
 */
beforeEach(function () {
    app()['env'] = 'production';
});

test('a plain request is sent to the same address over https', function (string $path) {
    $this->get('http://localhost'.$path)
        ->assertMovedPermanently()
        ->assertRedirect('https://localhost'.$path);
})->with([
    'a page' => '/listings',
    'a query string' => '/listings?search=jazz',
    'the Dashboard' => '/dashboard',
    'the Office' => '/office',
]);

test('the home page is sent to the https home page', function () {
    $this->get('http://localhost/')
        ->assertMovedPermanently()
        ->assertRedirect('https://localhost');
});

test('a plain form post is redirected without becoming a get', function () {
    $this->post('http://localhost/login', ['email' => 'someone@example.com'])
        ->assertStatus(308)
        ->assertRedirect('https://localhost/login');
});

test('a secure response tells the browser to stay on https', function () {
    $this->get('https://localhost/up')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age='.RequireHttps::HSTS_MAX_AGE);
});

test('a request a proxy received over https is served, not redirected', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/up')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');
});

test('outside production plain http is still served', function () {
    app()['env'] = 'local';

    $this->get('http://localhost/up')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');
});
