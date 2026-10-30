<x-mail::message>
# Thank you{{ $order->name ? ', '.\Illuminate\Support\Str::before($order->name, ' ') : '' }}

Your order **{{ $order->reference }}** is paid for.

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_name !== 'Standard' ? ' ('.$item->variant_name.')' : '' }} | {{ $item->quantity }} | £{{ number_format($item->subtotal() / 100, 2) }} |
@endforeach
| Subtotal | | £{{ number_format($order->subtotal / 100, 2) }} |
| {{ $collecting ? 'Collection' : 'UK postage' }} | | {{ $order->shipping === 0 ? 'Free' : '£'.number_format($order->shipping / 100, 2) }} |
| **Total** | | **£{{ number_format($order->total / 100, 2) }}** |
</x-mail::table>

@if ($collecting)
We'll email you again when it is ready to collect from {{ $collection['name'] }}, {{ implode(', ', $collection['address']) }}.
@else
We'll post it to:

{!! implode('<br>', array_map('e', \App\Models\Order::addressLines($order->shipping_address))) !!}

and email you when it is on its way.
@endif

<x-mail::button :url="$orderUrl">
See your order
</x-mail::button>

@if ($claimUrl)
Have an account with us, or want one? [Add this order to your account]({{ $claimUrl }}) to find it there with your others.
@endif

Questions? Just reply to this email.

Good on Paper
</x-mail::message>
