<?php

use App\Mail\Shop\NewOrderForOffice;
use App\Mail\Shop\OrderConfirmed;
use App\Mail\Shop\OrderPosted;
use App\Mail\Shop\OrderReadyForCollection;
use App\Mail\Shop\OrderRefunded;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopSettings;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Facades\Storage;

/**
 * What the order emails say.
 */
beforeEach(function () {
    // The confirmation asks the bucket for a JPEG of each line's picture.
    Storage::fake('do');
});
function paidOrderToPost(): Order
{
    $order = Order::factory()->paid()->create([
        'name' => 'Sam Reader',
        'email' => 'reader@example.com',
        'shipping_address' => ['name' => 'Sam Reader', 'line_one' => '2 Lansdown', 'town' => 'Stroud', 'postcode' => 'GL5 1BB', 'country' => 'GB'],
    ]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Logo tee', 'variant_name' => 'Black / M', 'quantity' => 2, 'unit_price' => 1250]);

    return $order;
}

test('the customer\'s confirmation lists what they bought and where it is going', function () {
    $order = paidOrderToPost();

    $mail = new OrderConfirmed($order);

    $mail->assertHasSubject("Your Good on Paper order {$order->reference}")
        ->assertHasReplyTo('orders@goodonpaper.info')
        ->assertSeeInHtml('Thank you, Sam')
        ->assertSeeInHtml('Logo tee (Black / M)')
        ->assertSeeInHtml('£25.00')
        ->assertSeeInHtml('2 Lansdown')
        ->assertSeeInHtml('signature=', false);
});

test('a collection order\'s confirmation says where to collect it', function () {
    $order = Order::factory()->paid()->collection()->create();
    OrderItem::factory()->for($order)->create();

    (new OrderConfirmed($order))
        ->assertSeeInHtml('ready to collect from Good on Paper, Centre for Science &amp; Art, Lansdown, Stroud, GL5 1BB', false)
        ->assertDontSeeInHtml('post it to');
});

test('the office\'s email says what to pack and links to the order', function () {
    $order = paidOrderToPost();

    (new NewOrderForOffice($order))
        ->assertHasSubject("New order {$order->reference} to post")
        ->assertSeeInHtml('Large letter')
        ->assertSeeInHtml('/resources/orders/'.$order->id)
        ->assertDontSeeInHtml('Oversold');
});

test('an oversold order is flagged to the office', function () {
    $order = paidOrderToPost();
    $order->update(['oversold' => true]);

    (new NewOrderForOffice($order))
        ->assertHasSubject("New order {$order->reference} to post - OVERSOLD")
        ->assertSeeInHtml('Oversold');
});

test('the posted email gives the tracking number and the address', function () {
    $order = paidOrderToPost();
    $order->update(['tracking_number' => 'RM123456789GB']);

    (new OrderPosted($order))
        ->assertHasSubject("Your Good on Paper order {$order->reference} is on its way")
        ->assertSeeInHtml('RM123456789GB')
        ->assertSeeInHtml('2 Lansdown');
});

test('the ready email says where and when to collect, and what to quote', function () {
    ShopSettings::current()->update(['collection_hours' => "Tuesday to Friday\n10am to 4pm"]);
    $order = Order::factory()->paid()->collection()->create();

    (new OrderReadyForCollection($order))
        ->assertHasSubject("Your Good on Paper order {$order->reference} is ready to collect")
        ->assertSeeInHtml('Lansdown')
        ->assertSeeInHtml('Tuesday to Friday')
        ->assertSeeInHtml($order->reference);
});

test('the refund email says how much went back and whether the order was cancelled', function () {
    $order = Order::factory()->paid()->create(['status' => OrderStatus::Cancelled]);

    (new OrderRefunded($order, 2850))
        ->assertSeeInHtml('cancelled your order')
        ->assertSeeInHtml('£28.50', false);
});
