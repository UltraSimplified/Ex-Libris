<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Whether the public can buy anything yet. The catalogue can be browsed
    | while this is off; the basket and checkout stay hidden until a live
    | test purchase has been made and refunded.
    |
    */

    'checkout_enabled' => (bool) env('SHOP_CHECKOUT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Checkout Window
    |--------------------------------------------------------------------------
    |
    | How many minutes a customer has on Stripe's payment page before the
    | order lapses and is cancelled. Stripe allows 30 at the least.
    |
    */

    'checkout_expires_after' => (int) env('SHOP_CHECKOUT_EXPIRES_AFTER', 30),

    /*
    |--------------------------------------------------------------------------
    | Office Notifications
    |--------------------------------------------------------------------------
    |
    | Who is emailed when an order is paid.
    |
    */

    'notify' => [
        'to' => env('SHOP_NOTIFY_TO', env('MAIL_FROM_ADDRESS')),
        'cc' => env('SHOP_NOTIFY_CC'),
    ],

];
