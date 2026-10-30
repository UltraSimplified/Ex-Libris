<?php

use App\Http\Controllers\EmbedController;
use App\Http\Controllers\WidgetClickController;
use Illuminate\Support\Facades\Route;

/*
 * Gig widgets, embedded in other people's websites. Registered in
 * bootstrap/app.php outside the web group - no session, no cookies, no
 * CSRF - and on the widget host when one is configured. The loader script
 * itself is the static file public/widget.js.
 */
Route::get('embed/{widget:key}', [EmbedController::class, 'show'])
    ->name('widgets.embed');

Route::get('embed/{widget:key}/tickets/{listing}', WidgetClickController::class)
    ->whereNumber('listing')
    ->middleware('throttle:60,1')
    ->name('widgets.click');
