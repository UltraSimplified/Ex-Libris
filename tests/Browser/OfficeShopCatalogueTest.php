<?php

use App\Models\Product;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

test('clicking a category opens its page, where its products have handles to drag', function () {
    $category = ShopCategory::factory()->create(['name' => 'Prints']);
    Product::factory()->for($category)->create(['name' => 'Record Deck']);

    visit('/office/resources/shop-categories')
        ->click('Prints')
        ->assertPathIs("/office/resources/shop-categories/{$category->id}")
        ->assertSee('Record Deck')
        ->assertPresent('.handle')
        ->assertNoJavaScriptErrors();
});

test('the main list of products has no handles, as categories are mixed there', function () {
    Product::factory()->create(['name' => 'Record Deck']);

    visit('/office/resources/products')
        ->assertSee('Record Deck')
        ->assertMissing('.handle')
        ->assertNoJavaScriptErrors();
});

test('the shop page\'s description shows in the list of settings and on the form it opens to', function () {
    $settings = ShopSettings::current();
    $settings->update(['meta_description' => 'Riso prints and records.']);

    visit('/office/resources/shop-settings')
        ->assertSee('Riso prints and records.')
        ->click('Riso prints and records.')
        ->assertPathIs("/office/resources/shop-settings/{$settings->id}/edit")
        ->assertSee('SHOP PAGE DESCRIPTION')
        ->assertNoJavaScriptErrors();
});
