<?php

namespace App\Events\Dashboard;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody has added a listing from their dashboard, from scratch or by
 * duplicating one of their own.
 */
class ListingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Listing $listing) {}
}
