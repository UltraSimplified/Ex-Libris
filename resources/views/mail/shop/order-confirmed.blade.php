<x-mail::message>
# Thank you{{ $order->name ? ', '.\Illuminate\Support\Str::before($order->name, ' ') : '' }}

Your order **{{ $order->reference }}** is paid for.

{{--
    Written out as a table rather than with <x-mail::table> and a markdown
    one, because a picture in a markdown table cell is not something every
    mail client renders. Nothing inside may be separated by a blank line:
    the markdown parser would close the raw block and print the rest of
    the tags as text.
--}}
<div class="table">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<thead>
<tr>
<th align="left" colspan="2">Item</th>
<th align="center">Qty</th>
<th align="right">Price</th>
</tr>
</thead>
<tbody>
@foreach ($lines as $line)
<tr>
<td width="80" valign="top">
@if ($line['picture'])
<img src="{{ $line['picture'] }}" width="64" height="64" alt="" style="display: block; width: 64px; height: 64px; border: 1px solid #edeff2;">
@endif
</td>
<td valign="top">{{ $line['name'] }}</td>
<td align="center" valign="top">{{ $line['quantity'] }}</td>
<td align="right" valign="top">&pound;{{ number_format($line['subtotal'] / 100, 2) }}</td>
</tr>
@endforeach
<tr>
<td colspan="3" valign="top">Subtotal</td>
<td align="right" valign="top">&pound;{{ number_format($order->subtotal / 100, 2) }}</td>
</tr>
<tr>
<td colspan="3" valign="top">{{ $collecting ? 'Collection' : 'UK postage' }}</td>
<td align="right" valign="top">{{ $order->shipping === 0 ? 'Free' : '£'.number_format($order->shipping / 100, 2) }}</td>
</tr>
<tr>
<td colspan="3" valign="top"><strong>Total</strong></td>
<td align="right" valign="top"><strong>&pound;{{ number_format($order->total / 100, 2) }}</strong></td>
</tr>
</tbody>
</table>
</div>

@if ($collecting)
We'll email you again when it is ready to collect from {{ $collection['name'] }}, {{ implode(', ', $collection['address']) }}.
@else
We'll post it to:

{!! implode('<br>', array_map('e', \App\Models\Order::addressLines($order->shipping_address))) !!}

and email you when it is on its way.
@endif

@if ($map)
<div>
<img src="{{ $map }}" width="{{ $mapSize['width'] }}" alt="A map of {{ $collecting ? 'where to collect your order' : 'where your order is going' }}" style="display: block; width: 100%; max-width: {{ $mapSize['width'] }}px; height: auto; border: 1px solid #edeff2;">
<div style="font-size: 12px; color: #74787e; padding-top: 4px;">&copy; <a href="https://www.openstreetmap.org/copyright" style="color: #74787e;">{{ $mapCredit }}</a></div>
</div>
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
