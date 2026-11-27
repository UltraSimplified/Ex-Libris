<?php

use App\Http\Controllers\RestoreUnsavedDraftController;
use Illuminate\Support\Facades\Route;

/*
 * The Office's own calls that Nova does not make itself, behind Nova's
 * sign-in and gate as Nova's are, and under nova-vendor beside the routes
 * of the Office's own fields.
 */

/*
 * An Office form's unsaved changes, sent back by the browser that kept them
 * to be put into the form when it next loads.
 */
Route::post('unsaved-drafts/{resource}/{resourceId?}', RestoreUnsavedDraftController::class)
    ->whereNumber('resourceId')
    ->name('office.unsaved-drafts.restore');
