<?php

namespace App\Services\Shop\Payments;

use App\Models\Address;
use App\Models\User;
use App\Services\Shop\StripeAddress;
use Laravel\Cashier\Cashier;

/**
 * The shop's way into Stripe, through Cashier's client.
 */
class StripePaymentGateway implements PaymentGateway
{
    public function createCheckoutSession(array $parameters): CheckoutSession
    {
        $session = Cashier::stripe()->checkout->sessions->create($parameters);

        return new CheckoutSession($session->id, $session->url);
    }

    public function retrieveCheckoutSession(string $id): array
    {
        return Cashier::stripe()->checkout->sessions->retrieve($id)->toArray();
    }

    public function customerFor(User $user): string
    {
        $customer = $user->createOrGetStripeCustomer();

        $billing = $user->billingAddress;
        $delivery = $user->postalAddress();

        $details = array_filter([
            'address' => $billing instanceof Address ? StripeAddress::fromAccount($billing) : null,
            'shipping' => $delivery instanceof Address
                ? ['name' => $user->name, 'address' => StripeAddress::fromAccount($delivery)]
                : null,
        ]);

        if ($details !== []) {
            $user->updateStripeCustomer($details);
        }

        return $customer->id;
    }

    public function receiptUrl(string $paymentIntentId): ?string
    {
        $intent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId, ['expand' => ['latest_charge']]);

        return $intent->latest_charge?->receipt_url;
    }

    public function refund(string $paymentIntentId, ?int $amount, string $idempotencyKey): void
    {
        Cashier::stripe()->refunds->create(
            array_filter(['payment_intent' => $paymentIntentId, 'amount' => $amount]),
            ['idempotency_key' => $idempotencyKey],
        );
    }
}
