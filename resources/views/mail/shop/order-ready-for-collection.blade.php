<x-mail::message>
# Ready to collect

Your order **{{ $order->reference }}** is waiting for you at:

**{{ $collection['name'] }}**<br>
{!! implode('<br>', array_map('e', $collection['address'])) !!}

@if ($map)
<div>
<img src="{{ $map }}" width="{{ $mapSize['width'] }}" alt="A map of where to collect your order" style="display: block; width: 100%; max-width: {{ $mapSize['width'] }}px; height: auto; border: 1px solid #edeff2;">
<div style="font-size: 12px; color: #74787e; padding-top: 4px;">&copy; <a href="https://www.openstreetmap.org/copyright" style="color: #74787e;">{{ $mapCredit }}</a></div>
</div>
@endif

@if ($collection['hours'])
**When:**<br>
{!! nl2br(e($collection['hours'])) !!}
@endif

Just give your name and the order reference, **{{ $order->reference }}**. If someone else is collecting it for you, let them know both.

Questions? Just reply to this email.

Good on Paper
</x-mail::message>
