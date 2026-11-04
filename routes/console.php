<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Bookmarks for events that have been and gone. Overnight, when nobody is
 * looking at the site, and not on top of itself if a run is ever slow.
 */
Schedule::command('bookmarks:prune --commit')
    ->dailyAt('03:20')
    ->withoutOverlapping();

/*
 * Photojournal photos chosen for a feature that was never saved.
 */
Schedule::command('galleries:prune --commit')
    ->dailyAt('03:40')
    ->withoutOverlapping();

/*
 * Orders Stripe's webhook never told the shop about.
 */
Schedule::command('shop:reconcile-orders --commit')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
