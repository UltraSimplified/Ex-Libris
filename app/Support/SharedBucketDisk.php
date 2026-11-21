<?php

namespace App\Support;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Log;

/**
 * A Spaces disk as staging and a local checkout are given it.
 *
 * Every site uses the one Spaces bucket, the live site's, but staging and a
 * local checkout read an older copy of its database. A file one of them
 * thinks is its own to delete may be one the live site still shows: in
 * November 2026 staging's nightly clear-down deleted the photos of a feature
 * that was in live's database and not in staging's copy. So, away from the
 * live site, nothing is deleted from the bucket or moved off its name,
 * whatever asks: Nova, a clear-down, or a listing edited in the Dashboard.
 * A file left behind costs pennies; one taken from live costs a feature.
 */
final class SharedBucketDisk extends AwsS3V3Adapter
{
    /**
     * Keep the files, and say so in the log.
     *
     * @param  string|array<int, string>  $paths
     */
    public function delete($paths): bool
    {
        $this->keep(is_array($paths) ? $paths : func_get_args());

        return true;
    }

    public function deleteDirectory($directory): bool
    {
        $this->keep([$directory.'/']);

        return true;
    }

    /**
     * Copy rather than move, so the file stays under the name live may use.
     */
    public function move($from, $to): bool
    {
        return $this->copy($from, $to);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function keep(array $paths): void
    {
        Log::info('Kept on the shared Spaces bucket: only the live site deletes from it.', ['paths' => $paths]);
    }
}
