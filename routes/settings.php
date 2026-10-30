<?php

use App\Http\Controllers\Settings\AddressController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Middleware\RefuseWhileSwitched;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware(RefuseWhileSwitched::class)
        ->name('profile.destroy');

    Route::get('settings/addresses', [AddressController::class, 'edit'])->name('addresses.edit');
    Route::patch('settings/addresses', [AddressController::class, 'update'])->name('addresses.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])
        ->middleware(RefuseWhileSwitched::class)
        ->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware(['throttle:6,1', RefuseWhileSwitched::class])
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->middleware(RefuseWhileSwitched::class)
        ->name('two-factor.show');

    /*
     * What an account chooses to hear from us: which mailings, and how
     * often. A stub - nothing records a preference yet, the mailing list
     * is still a form that posts away to a third party - so this holds
     * its place beside the rest of an account's settings.
     */
    Route::get('settings/notifications', function () {
        return Inertia::render('settings/Notifications');
    })->name('notifications.edit');
});
