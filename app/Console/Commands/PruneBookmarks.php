<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clears out bookmarks for events that have been and gone.
 *
 * Nobody is coming back to last month's pub quiz, and the table would
 * otherwise only ever grow. A bookmark whose listing has been deleted
 * goes with it already, by the foreign key.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database is the live one; the scheduler passes it.
 */
class PruneBookmarks extends Command
{
    protected $signature = 'bookmarks:prune
        {--commit : Actually delete}';

    protected $description = 'Remove bookmarks for events that have already finished';

    public function handle(): int
    {
        $expired = DB::table('bookmarks')->whereIn(
            'listing_id',
            Listing::query()
                ->whereRaw('DATE(COALESCE(end_date, start_date)) < ?', [today()->toDateString()])
                ->select('listings.id')
        );

        $count = $expired->count();

        if ($count === 0) {
            $this->info('Nothing to clear down.');

            return self::SUCCESS;
        }

        if (! $this->option('commit')) {
            $this->warn($count.' bookmark(s) would be cleared down. Re-run with --commit to delete.');

            return self::SUCCESS;
        }

        $expired->delete();

        $this->info('Cleared down '.$count.' bookmark(s).');

        return self::SUCCESS;
    }
}
