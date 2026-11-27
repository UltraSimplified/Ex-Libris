<?php

namespace App\Support;

use App\Listeners\MarkEachSignIn;
use Illuminate\Http\Request;
use Laravel\Nova\Contracts\ImpersonatesUsers;

/**
 * Who a browser is signed in as, for an Office tab working out whether it
 * can still save as the person who opened it.
 *
 * A switch to an account, a sign-out or a sign-in as somebody else in any
 * tab changes it for every tab, so the tab asks rather than assumes, and
 * takes the session's token with the answer: signing in again gives the
 * session a new one, and the form would otherwise send the old.
 */
final class SignedInAccount
{
    /**
     * The mark tells one sign-in from the next, a switch to the same account
     * again included: see MarkEachSignIn.
     *
     * @return array{name: string|null, office: bool, switched: bool, signIn: string|null, csrfToken: string, switchBackUrl: string}
     */
    public static function describe(Request $request): array
    {
        $user = $request->user();

        return [
            'name' => $user?->name,
            'office' => $user?->can('viewNova') ?? false,
            'switched' => app(ImpersonatesUsers::class)->impersonating($request),
            'signIn' => $request->session()->get(MarkEachSignIn::SESSION_KEY),
            'csrfToken' => $request->session()->token(),
            'switchBackUrl' => route('switch-back', absolute: false),
        ];
    }
}
