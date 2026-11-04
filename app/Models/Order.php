<?php

namespace App\Models;

use App\Support\Shop\Fulfilment;
use App\Support\Shop\InvalidOrderTransition;
use App\Support\Shop\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * An order from the shop.
 *
 * Made Pending when the customer is sent to Stripe; Stripe's webhook says
 * when it is paid, and the office takes it from there. Its status only ever
 * moves by transitionTo(), which holds the order to the moves OrderStatus
 * allows. Money is in pence.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Where order references start, so the first is GOP-10001 rather than
     * GOP-1.
     */
    private const REFERENCE_OFFSET = 10000;

    /**
     * The columns' database defaults, so a new order holds them before it
     * is read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'postage_capped' => false,
        'currency' => 'gbp',
        'oversold' => false,
        'refunded_amount' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'name',
        'phone',
        'status',
        'fulfilment',
        'subtotal',
        'shipping',
        'total',
        'postage_capped',
        'currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'receipt_url',
        'billing_address',
        'shipping_address',
        'oversold',
        'refunded_amount',
        'tracking_number',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfilment' => Fulfilment::class,
            'subtotal' => 'integer',
            'shipping' => 'integer',
            'total' => 'integer',
            'postage_capped' => 'boolean',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'delivery_latitude' => 'float',
            'delivery_longitude' => 'float',
            'oversold' => 'boolean',
            'refunded_amount' => 'integer',
            'paid_at' => 'datetime',
            'posted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'ready_at' => 'datetime',
            'collected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Give every order its reference as soon as it has an id.
     */
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            $order->reference = 'GOP-'.($order->id + self::REFERENCE_OFFSET);
            $order->saveQuietly();
        });
    }

    /**
     * The account that placed it, if it was not placed as a guest.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What was bought.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders that were paid for, whatever has happened to them since -
     * not the ones abandoned on Stripe's page.
     */
    #[Scope]
    protected function paidFor(Builder $query): Builder
    {
        return $query->whereNotNull('paid_at');
    }

    /**
     * Move the order on, stamping when it got there.
     *
     * @throws InvalidOrderTransition when the move is not one OrderStatus allows
     */
    public function transitionTo(OrderStatus $status): void
    {
        if (! $this->status->canMoveTo($status, $this->fulfilment)) {
            throw InvalidOrderTransition::between($this->status, $status, $this->fulfilment);
        }

        $this->status = $status;

        if ($column = $status->timestampColumn()) {
            $this->{$column} = now();
        }

        $this->save();
    }

    /**
     * Add a dated line to the office's notes, so what was done to an order,
     * and by whom, can be read back.
     */
    public function addNote(string $note): void
    {
        $line = '['.now()->format('j M Y H:i').'] '.$note;

        $this->notes = filled($this->notes) ? $this->notes."\n".$line : $line;
        $this->save();
    }

    /**
     * What can still be refunded, in pence.
     */
    public function refundable(): int
    {
        return max(0, $this->total - $this->refunded_amount);
    }

    /**
     * How many things were bought, counting each one.
     */
    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * A link to the order that only the person sent it can open, for guests
     * who have no account to see it in.
     */
    public function guestUrl(): string
    {
        return URL::signedRoute('orders.show', ['order' => $this->reference]);
    }

    /**
     * A link that adds this order to whichever account opens it, for the
     * confirmation email of an order placed as a guest.
     */
    public function claimUrl(): string
    {
        return URL::signedRoute('orders.claim', ['order' => $this->reference]);
    }

    /**
     * An address as kept on the order, a line at a time.
     *
     * @param  array<string, ?string>|null  $address
     * @return list<string>
     */
    public static function addressLines(?array $address): array
    {
        if ($address === null) {
            return [];
        }

        return array_values(array_filter([
            $address['name'] ?? null,
            $address['line_one'] ?? null,
            $address['line_two'] ?? null,
            $address['town'] ?? null,
            $address['county'] ?? null,
            $address['postcode'] ?? null,
            $address['country'] ?? null,
        ], 'filled'));
    }
}
