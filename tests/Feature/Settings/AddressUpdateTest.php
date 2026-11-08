<?php

use App\Models\Address;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * @return array<string, string>
 */
function billingFields(array $overrides = []): array
{
    return array_merge([
        'line_one' => '12 Lansdown',
        'town' => 'Stroud',
        'county' => 'Gloucestershire',
        'postcode' => 'GL5 1BB',
        'country' => 'United Kingdom',
    ], $overrides);
}

test('the addresses page is displayed', function () {
    actingAs(User::factory()->create())
        ->get(route('addresses.edit'))
        ->assertOk();
});

test('a billing address can be saved', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('addresses.update'), [
            'billing' => billingFields(),
            'delivery_same_as_billing' => 'on',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('addresses.edit'));

    $billing = $user->refresh()->billingAddress;

    expect($billing)->not->toBeNull();
    expect($billing->line_one)->toBe('12 Lansdown');
    expect($billing->postcode)->toBe('GL5 1BB');
    expect($user->deliveryAddress)->toBeNull();
});

test('a delivery address is saved when it differs from the billing address', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('addresses.update'), [
            'billing' => billingFields(),
            'delivery_same_as_billing' => false,
            'delivery' => billingFields(['line_one' => 'Lansdown Hall', 'postcode' => 'GL5 1BN']),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->deliveryAddress->line_one)->toBe('Lansdown Hall');
    expect($user->billingAddress->line_one)->toBe('12 Lansdown');
});

test('ticking same as billing puts away a delivery address already on file', function () {
    $user = User::factory()->create();
    Address::factory()->delivery()->for($user)->create();

    actingAs($user)
        ->patch(route('addresses.update'), [
            'billing' => billingFields(),
            'delivery_same_as_billing' => 'on',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->deliveryAddress)->toBeNull();
    expect(Address::where('user_id', $user->id)->count())->toBe(1);
});

test('a delivery address is required when it is not the same as billing', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('addresses.edit'))
        ->patch(route('addresses.update'), [
            'billing' => billingFields(),
            'delivery_same_as_billing' => false,
        ])
        ->assertSessionHasErrors('delivery');

    expect($user->refresh()->billingAddress)->toBeNull();
});

test('a billing address needs a street, a town and a postcode', function () {
    actingAs(User::factory()->create())
        ->from(route('addresses.edit'))
        ->patch(route('addresses.update'), [
            'billing' => ['country' => 'United Kingdom'],
            'delivery_same_as_billing' => 'on',
        ])
        ->assertSessionHasErrors([
            'billing.line_one',
            'billing.town',
            'billing.postcode',
        ]);
});

test('saving twice edits the address rather than adding another', function () {
    $user = User::factory()->create();

    foreach (['12 Lansdown', '13 Lansdown'] as $line) {
        actingAs($user)
            ->patch(route('addresses.update'), [
                'billing' => billingFields(['line_one' => $line]),
                'delivery_same_as_billing' => 'on',
            ])
            ->assertSessionHasNoErrors();
    }

    expect(Address::where('user_id', $user->id)->count())->toBe(1);
    expect($user->refresh()->billingAddress->line_one)->toBe('13 Lansdown');
});

test('the post goes to the delivery address when there is one', function () {
    $user = User::factory()->create();
    $billing = Address::factory()->for($user)->create();

    expect($user->postalAddress()->is($billing))->toBeTrue();

    $delivery = Address::factory()->delivery()->for($user)->create();

    expect($user->refresh()->postalAddress()->is($delivery))->toBeTrue();
});

test('addresses go when the account does', function () {
    $user = User::factory()->create(['avatar' => null]);
    Address::factory()->for($user)->create();

    $user->delete();

    expect(Address::where('user_id', $user->id)->count())->toBe(0);
});

test('a signed out visitor cannot reach the addresses page', function () {
    $this->get(route('addresses.edit'))->assertRedirect(route('login'));
});
