<?php

use App\Models\Advert;
use App\Models\AdvertEvent;

const GOOGLEBOT_SMARTPHONE = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/**
 * A crawler that follows an advert's link has not clicked on it, so it is
 * sent on as anybody would be, and the advert's figures are left alone.
 */
test('a crawler following an advert link is not counted as a click', function (string $userAgent) {
    $advert = Advert::factory()->create(['link' => 'https://example.com/']);

    $this->withHeader('User-Agent', $userAgent)
        ->get("/ad/{$advert->id}/click")
        ->assertRedirectContains('https://example.com/');

    expect(AdvertEvent::query()->count())->toBe(0);
})->with([
    'Googlebot' => GOOGLEBOT_SMARTPHONE,
    'Bing' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'Google Ads' => 'AdsBot-Google (+http://www.google.com/adsbot.html)',
    'AdSense' => 'Mediapartners-Google',
    'a generic crawler' => 'SomeSiteCrawler/1.0',
    'a script' => 'curl/8.7.1',
    'no name at all' => '',
]);

test('a reader following an advert link is counted as a click', function (string $userAgent) {
    $advert = Advert::factory()->create(['link' => 'https://example.com/']);

    $this->withHeader('User-Agent', $userAgent)
        ->get("/ad/{$advert->id}/click")
        ->assertRedirectContains('https://example.com/');

    expect(AdvertEvent::query()->where('type', 'click')->count())->toBe(1);
})->with([
    'an iPhone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1',
    'an Android phone' => 'Mozilla/5.0 (Linux; Android 15; SM-S938B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36',
    'a phone whose maker ends in bot' => 'Mozilla/5.0 (Linux; Android 13; CUBOT KingKong) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36',
]);
