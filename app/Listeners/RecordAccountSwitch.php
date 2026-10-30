<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Nova\Events\StartedImpersonating;
use Laravel\Nova\Events\StoppedImpersonating;

/**
 * Notes in the log whenever staff switch to somebody's account from the
 * Office and back, so anything done in that account can be traced to who
 * was really there.
 *
 * Switching in also gives the session a new id, as signing in does.
 */
class RecordAccountSwitch
{
    public function handle(StartedImpersonating|StoppedImpersonating $event): void
    {
        $started = $event instanceof StartedImpersonating;

        if ($started && request()->hasSession()) {
            request()->session()->regenerate();
        }

        Log::notice($started ? 'Switched to an account from the Office.' : 'Switched back from an account.', [
            'staff' => $event->impersonator->getKey().' '.$event->impersonator->email,
            'account' => $event->impersonated?->getKey().' '.$event->impersonated?->email,
            'ip' => request()->ip(),
        ]);
    }
}
