<x-mail::message>
# New order {{ $order->reference }}

@if ($order->oversold)
**Oversold:** at least one line took the last of its stock as someone else bought it too. Check the stock before packing, and refund or offer a swap.
@endif

**{{ $order->name }}** ({{ $order->email }}{{ $order->phone ? ', '.$order->phone : '' }}) paid **£{{ number_format($order->total / 100, 2) }}**, {{ $collecting ? 'to collect' : 'to post' }}.

<x-mail::table>
| Item | Qty | Packing |
|:-----|:---:|:--------|
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_name !== 'Standard' ? ' ('.$item->variant_name.')' : '' }} | {{ $item->quantity }} | {{ $item->postage_band }} |
@endforeach
</x-mail::table>

@unless ($collecting)
**Post to:**<br>
{!! implode('<br>', array_map('e', \App\Models\Order::addressLines($order->shipping_address))) !!}
@endunless

<x-mail::button :url="$officeUrl">
Open in the Office
</x-mail::button>
</x-mail::message>
