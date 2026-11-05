<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Whether a request comes from someone whose reading the Office counts:
 * not a crawler, and not one of the Office's own people, who read the
 * public site all day while they work on it and would otherwise be its
 * most devoted readers.
 */
class Reader
{
    public static function counts(Request $request): bool
    {
        return ! Crawler::sent($request)
            && ! Gate::forUser($request->user())->allows('viewNova');
    }
}
