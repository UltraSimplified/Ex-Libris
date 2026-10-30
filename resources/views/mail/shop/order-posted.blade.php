<x-mail::message>
# It's on its way

Your order **{{ $order->reference }}** went in the post today.

@if ($order->tracking_number)
Your tracking number is **{{ $order->tracking_number }}**.
@endif

It's going to:

{!! implode('<br>', array_map('e', \App\Models\Order::addressLines($order->shipping_address))) !!}

<x-mail::button :url="$orderUrl">
See your order
</x-mail::button>

Questions? Just reply to this email.

Good on Paper
</x-mail::message>
