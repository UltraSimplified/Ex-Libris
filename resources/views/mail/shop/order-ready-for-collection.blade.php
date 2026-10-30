<x-mail::message>
# Ready to collect

Your order **{{ $order->reference }}** is waiting for you at:

**{{ $collection['name'] }}**<br>
{!! implode('<br>', array_map('e', $collection['address'])) !!}

@if ($collection['hours'])
**When:**<br>
{!! nl2br(e($collection['hours'])) !!}
@endif

Just give your name and the order reference, **{{ $order->reference }}**. If someone else is collecting it for you, let them know both.

Questions? Just reply to this email.

Good on Paper
</x-mail::message>
