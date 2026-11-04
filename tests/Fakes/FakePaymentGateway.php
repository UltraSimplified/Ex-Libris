<?php

namespace Tests\Fakes;

use App\Models\User;
use App\Services\Shop\Payments\CheckoutSession;
use App\Services\Shop\Payments\PaymentGateway;
use RuntimeException;

/**
 * Stands in for Stripe in tests: remembers what it was asked and answers
 * with made-up sessions, so nothing ever reaches the live account.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var list<array<string, mixed>> */
    public array $sessions = [];

    /**
     * What Stripe would answer about a session, by its id.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $sessionsAtStripe = [];

    /** @var list<string> */
    public array $sessionsAskedAbout = [];

    /** @var list<array{payment_intent: string, amount: ?int, key: string}> */
    public array $refunds = [];

    /** @var list<int> */
    public array $customersFor = [];

    public bool $failNextCheckout = false;

    public function createCheckoutSession(array $parameters): CheckoutSession
    {
        if ($this->failNextCheckout) {
            $this->failNextCheckout = false;

            throw new RuntimeException('Stripe is down.');
        }

        $this->sessions[] = $parameters;
        $id = 'cs_test_fake_'.count($this->sessions);

        return new CheckoutSession($id, "https://checkout.stripe.test/{$id}");
    }

    public function retrieveCheckoutSession(string $id): array
    {
        $this->sessionsAskedAbout[] = $id;

        return $this->sessionsAtStripe[$id] ?? throw new RuntimeException("No such checkout.session: {$id}");
    }

    public function customerFor(User $user): string
    {
        $this->customersFor[] = $user->id;

        return 'cus_test_'.$user->id;
    }

    public function receiptUrl(string $paymentIntentId): ?string
    {
        return "https://pay.stripe.test/receipts/{$paymentIntentId}";
    }

    public function refund(string $paymentIntentId, ?int $amount, string $idempotencyKey): void
    {
        $this->refunds[] = ['payment_intent' => $paymentIntentId, 'amount' => $amount, 'key' => $idempotencyKey];
    }

    /**
     * The parameters of the last session opened.
     *
     * @return array<string, mixed>
     */
    public function lastSession(): array
    {
        return $this->sessions[array_key_last($this->sessions)] ?? [];
    }
}
