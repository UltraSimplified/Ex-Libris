<?php

namespace App\Services\Shop\Payments;

use App\Models\User;

/**
 * Everything the shop asks of Stripe, in one place, so that tests can stand
 * a fake in its place and never reach the live account.
 */
interface PaymentGateway
{
    /**
     * Open a Stripe Checkout session with the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function createCheckoutSession(array $parameters): CheckoutSession;

    /**
     * Read a Checkout session back from Stripe, as an array.
     *
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $id): array;

    /**
     * The Stripe customer for an account, made if it has none, and given
     * the account's addresses so Stripe's form opens filled in.
     */
    public function customerFor(User $user): string;

    /**
     * The address of Stripe's receipt for a payment, if it has one.
     */
    public function receiptUrl(string $paymentIntentId): ?string;

    /**
     * Refund some or all of a payment. The key stops the same refund being
     * made twice if the request is repeated.
     */
    public function refund(string $paymentIntentId, ?int $amount, string $idempotencyKey): void;
}
