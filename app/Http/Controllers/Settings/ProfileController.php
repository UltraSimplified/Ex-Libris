<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\Images\UserAvatar;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private UserAvatar $avatars,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $previousAvatar = $user->avatar;

        $user->fill($request->safe()->only(['name', 'email']));

        /*
         * The byline, which is the same picture and the same name as the
         * profile with a note under it. Written only for a writer: these
         * are kept out of $fillable so that somebody who was never
         * offered the fields cannot post one and have it saved.
         */
        if ($user->hasRole('is_writer')) {
            $user->bio = $request->safe()->string('bio')->trim()->value() ?: null;
            $user->avatar_alt_text = $request->safe()->string('avatar_alt_text')->trim()->value() ?: null;
        }

        if ($request->hasFile('avatar')) {
            $user->avatar = $this->avatars->store($request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar = null;
        }

        // A description of a picture that is no longer there describes
        // nothing, so it goes with the picture.
        if ($user->avatar === null) {
            $user->avatar_alt_text = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // The old picture only goes once the row pointing at the new one has
        // saved, so a failure here leaves the user with the avatar they had.
        if ($user->wasChanged('avatar')) {
            $this->avatars->delete($previousAvatar);
        }

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
