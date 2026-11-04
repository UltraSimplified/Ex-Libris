<?php

namespace App\Console\Commands;

use App\Mail\Shop\WebhookLooksBroken;
use App\Models\Order;
use App\Services\Shop\OrderPayments;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Catches the orders Stripe's webhook never told the shop about.
 *
 * Only the webhook moves an order on from Pending, so one that does not get
 * through leaves a customer who has paid with an order the shop is not
 * acting on. This asks Stripe what became of each order still waiting, and
 * applies the answer the way the webhook would have, stock and emails included.
 *
 * An order that had to be marked paid means the webhook is not working, which
 * would otherwise go unnoticed now this is covering for it, so the office is
 * told.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database may be a shared one; the scheduler passes it.
 */
class ReconcileOrders extends Command
{
    /**
     * Longer than Stripe's half hour on the payment page, so an order still
     * waiting has had time to be settled by the webhook.
     */
    private const HOURS_BEFORE_ASKING = 1;

    /**
     * An order this old was left behind by something else, such as a change of
     * Stripe account, and asking again every hour will not settle it. The
     * Office's stuck orders metric still counts it.
     */
    private const DAYS_BEFORE_GIVING_UP = 7;

    protected $signature = 'shop:reconcile-orders
        {--commit : Actually change the orders}';

    protected $description = 'Ask Stripe about orders still awaiting payment, in case a webhook was missed';

    public function handle(PaymentGateway $gateway, OrderPayments $payments): int
    {
        $markedPaid = new Collection;
        $failures = 0;

        foreach ($this->ordersStillWaiting() as $order) {
            try {
                $session = $gateway->retrieveCheckoutSession($order->stripe_checkout_session_id);

                $this->line($order->reference.': Stripe says '.($session['status'] ?? 'nothing').', '.($session['payment_status'] ?? 'nothing').'.');

                $settled = $this->option('commit') ? $this->settle($session, $payments) : null;

                if ($settled?->status === OrderStatus::Paid) {
                    $markedPaid->push($settled);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error($order->reference.' could not be checked: '.$exception->getMessage());
                $failures++;
            }
        }

        $this->tellTheOffice($markedPaid);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersStillWaiting(): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->whereNotNull('stripe_checkout_session_id')
            ->whereBetween('created_at', [now()->subDays(self::DAYS_BEFORE_GIVING_UP), now()->subHours(self::HOURS_BEFORE_ASKING)])
            ->oldest('id')
            ->get();
    }

    /**
     * Apply what Stripe says. A session still open has time left, and one
     * complete but unpaid is waiting on a slower kind of payment, so neither
     * changes anything.
     *
     * @param  array<string, mixed>  $session
     */
    private function settle(array $session, OrderPayments $payments): ?Order
    {
        $order = match ($session['status'] ?? null) {
            'complete' => $payments->completed($session),
            'expired' => $payments->expired($session),
            default => null,
        };

        $order?->addNote(($order->status === OrderStatus::Paid ? 'Marked paid' : 'Cancelled').' by the hourly check with Stripe, as its webhook did not arrive.');

        return $order;
    }

    /**
     * Sent straight away rather than queued, so it still goes if the queue is
     * what has stopped.
     *
     * @param  Collection<int, Order>  $markedPaid
     */
    private function tellTheOffice(Collection $markedPaid): void
    {
        if ($markedPaid->isEmpty()) {
            return;
        }

        Mail::to(config('shop.notify.to'))
            ->cc(array_filter([config('shop.notify.cc')]))
            ->send(new WebhookLooksBroken($markedPaid));
    }
}
