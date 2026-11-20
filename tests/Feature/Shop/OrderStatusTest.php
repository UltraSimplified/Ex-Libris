<?php

use App\Models\Order;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\InvalidOrderTransition;
use App\Support\Shop\OrderStatus;

/**
 * Where an order may go next depends on whether it is posted or collected.
 */
test('an order moves only as its fulfilment allows', function (Fulfilment $fulfilment, OrderStatus $from, OrderStatus $to, bool $allowed) {
    expect($from->canMoveTo($to, $fulfilment))->toBe($allowed);
})->with([
    'post: paid to posted' => [Fulfilment::Post, OrderStatus::Paid, OrderStatus::Posted, true],
    'post: posted to delivered' => [Fulfilment::Post, OrderStatus::Posted, OrderStatus::Delivered, true],
    'post: paid to ready' => [Fulfilment::Post, OrderStatus::Paid, OrderStatus::Ready, false],
    'post: posted to cancelled' => [Fulfilment::Post, OrderStatus::Posted, OrderStatus::Cancelled, false],
    'collection: paid to ready' => [Fulfilment::Collection, OrderStatus::Paid, OrderStatus::Ready, true],
    'collection: ready to collected' => [Fulfilment::Collection, OrderStatus::Ready, OrderStatus::Collected, true],
    'collection: ready to cancelled' => [Fulfilment::Collection, OrderStatus::Ready, OrderStatus::Cancelled, true],
    'collection: paid to posted' => [Fulfilment::Collection, OrderStatus::Paid, OrderStatus::Posted, false],
    'nothing to send: pending to paid' => [Fulfilment::None, OrderStatus::Pending, OrderStatus::Paid, true],
    'nothing to send: paid to cancelled' => [Fulfilment::None, OrderStatus::Paid, OrderStatus::Cancelled, true],
    'nothing to send: paid to posted' => [Fulfilment::None, OrderStatus::Paid, OrderStatus::Posted, false],
    'nothing to send: paid to ready' => [Fulfilment::None, OrderStatus::Paid, OrderStatus::Ready, false],
    'pending to posted' => [Fulfilment::Post, OrderStatus::Pending, OrderStatus::Posted, false],
    'cancelled to paid' => [Fulfilment::Post, OrderStatus::Cancelled, OrderStatus::Paid, false],
]);

test('a paid order with nothing to send is not left waiting on the office', function () {
    Order::factory()->paid()->nothingToSend()->create();

    expect(Order::query()->awaitingOffice()->exists())->toBeFalse();
});

test('moving an order stamps when it got there', function () {
    $order = Order::factory()->paid()->create();

    $order->transitionTo(OrderStatus::Posted);

    expect($order->fresh()->status)->toBe(OrderStatus::Posted)
        ->and($order->fresh()->posted_at)->not->toBeNull();
});

test('a move that is not allowed is refused and changes nothing', function () {
    $order = Order::factory()->paid()->collection()->create();

    expect(fn () => $order->transitionTo(OrderStatus::Posted))->toThrow(InvalidOrderTransition::class)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});
