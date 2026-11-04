<x-mail::message>
# Stripe's webhook looks broken

The hourly check with Stripe found orders that had been paid for without its webhook telling us. They are now marked paid and their emails are on the way, so these customers need nothing further:

@foreach ($orders as $order)
- [{{ $order->reference }}]({{ $officeUrl($order) }}): {{ $order->name }}, £{{ number_format($order->total / 100, 2) }}
@endforeach

Until the webhook is fixed, customers can wait up to two hours for their confirmation, and refunds made in Stripe's dashboard will not reach the Office.

**In Stripe, under Developers and Webhooks, check that:**

- the endpoint is enabled, and its address is {{ $webhookUrl }}
- its recent deliveries are not failing, as each one shows the answer it got
- its signing secret matches `STRIPE_WEBHOOK_SECRET` on the server
</x-mail::message>
