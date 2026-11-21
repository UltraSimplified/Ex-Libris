<?php

namespace App\Console\Commands;

use App\Models\Feature;
use App\Services\Images\FeatureGallery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

/**
 * Clears out Photojournal photos that no feature shows.
 *
 * A photo is stored the moment it is chosen in Nova, before the feature is
 * saved, so one chosen for a feature that is then never saved belongs to
 * nobody. Saving and deleting features clear up after themselves; this
 * catches what is left. A file is given a day's grace, so nothing is taken
 * from under somebody still editing.
 *
 * Runs as a dry run unless --commit is passed, because the configured disk
 * is the live one; the scheduler passes it. Only the live site may commit:
 * staging and local checkouts share its disk but not its database, so to
 * them every photo of a feature added on live since their copy was taken
 * looks like a stray.
 */
class PruneFeatureGalleries extends Command
{
    protected $signature = 'galleries:prune
        {--commit : Actually delete}';

    protected $description = 'Remove Photojournal photos that no feature shows';

    public function handle(FeatureGallery $gallery): int
    {
        if ($this->option('commit') && ! app()->isProduction()) {
            $this->error('Only the live site can clear down photos: this site shares its disk, but not its database, so it would delete photos live still shows.');

            return self::FAILURE;
        }

        $used = [];

        Feature::query()
            ->select(['id', 'body'])
            ->where('body', 'like', '%"gallery"%')
            ->lazyById()
            ->each(function (Feature $feature) use ($gallery, &$used) {
                foreach ($gallery->paths($feature->body) as $path) {
                    $used[$path] = true;
                }
            });

        $cutoff = now()->subDay()->getTimestamp();

        $orphans = collect(Storage::disk(FeatureGallery::DISK)->listContents(FeatureGallery::DIRECTORY, false))
            ->filter(fn ($item): bool => $item instanceof FileAttributes
                && ! isset($used[$item->path()])
                && ($item->lastModified() ?? $cutoff) < $cutoff)
            ->map(fn (FileAttributes $item): string => $item->path())
            ->values();

        if ($orphans->isEmpty()) {
            $this->info('Nothing to clear down.');

            return self::SUCCESS;
        }

        if (! $this->option('commit')) {
            $this->warn($orphans->count().' file(s) would be cleared down. Re-run with --commit to delete.');

            return self::SUCCESS;
        }

        Storage::disk(FeatureGallery::DISK)->delete($orphans->all());

        $this->info('Cleared down '.$orphans->count().' file(s).');

        return self::SUCCESS;
    }
}
