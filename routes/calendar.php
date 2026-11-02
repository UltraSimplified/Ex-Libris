<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

/*
 * Calendar files, fetched by phones and calendar apps. Registered in
 * bootstrap/app.php outside the web group - no session, no cookies - as
 * nothing in them depends on who is asking.
 */
Route::get('calendar/listings/{listing}.ics', CalendarController::class)
    ->whereNumber('listing')
    ->middleware('throttle:60,1')
    ->name('calendar.listing');
