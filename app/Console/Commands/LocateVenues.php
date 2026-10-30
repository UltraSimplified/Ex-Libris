<?php

namespace App\Console\Commands;

use App\Models\Venue;
use Illuminate\Console\Command;

class LocateVenues extends Command
{
    /**
     * @var string
     */
    protected $signature = 'venues:locate {--dry-run : List the venues that would be looked up, and change nothing}';

    /**
     * @var string
     */
    protected $description = 'Put on the map every venue with a postcode but no place yet';

    /**
     * Venues are placed when they are saved, so this is for the ones saved
     * before that began. Each is saved again, which looks its postcode up;
     * a short pause between them keeps well within postcodes.io's fair use.
     */
    public function handle(): int
    {
        $venues = Venue::query()
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->whereNotNull('postcode')
            ->where('postcode', '!=', '')
            ->orderBy('id')
            ->get();

        if ($venues->isEmpty()) {
            $this->info('Every venue with a postcode is already on the map.');

            return self::SUCCESS;
        }

        $placed = 0;

        foreach ($venues as $venue) {
            if ($this->option('dry-run')) {
                $this->line("{$venue->id}  {$venue->name}  {$venue->postcode}");

                continue;
            }

            $venue->save();

            if ($venue->latitude !== null) {
                $placed++;
                $this->line("Placed {$venue->name} ({$venue->postcode})");
            } else {
                $this->warn("Could not place {$venue->name} ({$venue->postcode})");
            }

            usleep(200_000);
        }

        if (! $this->option('dry-run')) {
            $this->info("Placed {$placed} of {$venues->count()} venues.");
        }

        return self::SUCCESS;
    }
}
