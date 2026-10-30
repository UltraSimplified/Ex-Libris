<x-mail::message>
# Your refund

@if ($cancelled)
We've cancelled your order **{{ $order->reference }}** and refunded **{{ $refunded }}**.
@else
We've refunded **{{ $refunded }}** from your order **{{ $order->reference }}**.
@endif

It goes back to the card you paid with, and usually shows within 5 to 10 working days.

Questions? Just reply to this email.

Good on Paper
</x-mail::message>
