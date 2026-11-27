<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Gives each sign-in its own mark in the session: signing in, switching to
 * an account from the Office and switching back are all sign-ins.
 *
 * An Office tab told its browser is no longer the Office's tells one of
 * these from the next by the mark (see SignedInAccount), so a notice put
 * aside for one switch comes back for the next, into the same account or
 * any other, without the tab having seen the switch back in between.
 */
class MarkEachSignIn
{
    public const SESSION_KEY = 'sign_in_mark';

    public function handle(Login $event): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, Str::random(16));
        }
    }
}
