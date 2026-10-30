<?php

use App\Services\Shop\Postage;
use App\Support\Shop\Fulfilment;

/**
 * Postage: each item pays its band, the total stops at the cap, and
 * collection is free. Amounts are the seeded placeholders - Letter £2,
 * Large letter £3, Parcel £4, Tube £6 - with a £10 cap.
 */
test('posted orders pay each item\'s band up to the cap', function (array $items, int $charged, bool $capped) {
    $quote = Postage::quote($items, Fulfilment::Post, 1000);

    expect($quote->amount)->toBe($charged)
        ->and($quote->isCapped())->toBe($capped);
})->with([
    'one small print' => [[['amount' => 200, 'quantity' => 1]], 200, false],
    'a t-shirt and an LP' => [[['amount' => 300, 'quantity' => 1], ['amount' => 400, 'quantity' => 1]], 700, false],
    'two of the same shirt' => [[['amount' => 300, 'quantity' => 2]], 600, false],
    'a penny under the cap' => [[['amount' => 999, 'quantity' => 1]], 999, false],
    'exactly the cap' => [[['amount' => 600, 'quantity' => 1], ['amount' => 400, 'quantity' => 1]], 1000, false],
    'a penny over the cap' => [[['amount' => 1001, 'quantity' => 1]], 1000, true],
    'three shirts and two posters' => [[['amount' => 300, 'quantity' => 3], ['amount' => 600, 'quantity' => 2]], 1000, true],
    'nothing' => [[], 0, false],
]);

test('a capped quote remembers what the items would have come to', function () {
    $quote = Postage::quote([['amount' => 300, 'quantity' => 3], ['amount' => 600, 'quantity' => 2]], Fulfilment::Post, 1000);

    expect($quote->toArray())->toBe(['amount' => 1000, 'uncapped' => 2100, 'capped' => true]);
});

test('collected orders pay no postage, however much is in them', function () {
    $quote = Postage::quote([['amount' => 600, 'quantity' => 10]], Fulfilment::Collection, 1000);

    expect($quote->amount)->toBe(0)
        ->and($quote->isCapped())->toBeFalse();
});
