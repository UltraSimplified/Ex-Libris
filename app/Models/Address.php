<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somewhere an account is billed at, or has the magazine sent to.
 *
 * An account keeps at most one of each kind. A delivery address that is
 * absent means the post goes to the billing address - there is no separate
 * flag saying so, because a flag and a row can disagree and the absence
 * cannot.
 */
class Address extends Model
{
    use HasFactory;

    public const BILLING = 'billing';

    public const DELIVERY = 'delivery';

    /**
     * The kinds of address an account may hold.
     *
     * @var list<string>
     */
    public const TYPES = [self::BILLING, self::DELIVERY];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'line_one',
        'line_two',
        'town',
        'county',
        'postcode',
        'country',
    ];

    /**
     * The account this address belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The address written out a line at a time, skipping what is missing.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->line_one,
            $this->line_two,
            $this->town,
            $this->county,
            $this->postcode,
            $this->country,
        ], fn (?string $line): bool => filled($line)));
    }
}
