<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Images\UserAvatar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Brings profile pictures uploaded before the 250 x 250 limit down to it.
 *
 * Originals stay in the bucket: live and staging share it, so a file this
 * database has finished with may still be the other one's avatar. Each change
 * is noted in storage/app as it is made, which is also the way back.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database may be the live one.
 */
class ResizeAvatars extends Command
{
    protected $signature = 'avatars:resize
        {--commit : Actually store the copies and update the accounts}';

    protected $description = 'Shrink existing profile pictures to the upload limit, keeping the originals';

    public function handle(UserAvatar $avatars): int
    {
        $log = 'avatar-resize-'.now()->format('Y-m-d-His').'.jsonl';
        $resized = $unchanged = 0;
        $unreadable = [];

        User::query()
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->lazyById()
            ->each(function (User $user) use ($avatars, $log, &$resized, &$unchanged, &$unreadable): void {
                try {
                    if (! $avatars->isOversized($user->avatar)) {
                        $unchanged++;

                        return;
                    }

                    if ($this->option('commit')) {
                        $this->replace($user, $avatars, $log);
                    }

                    $resized++;
                } catch (Throwable $e) {
                    // One bad avatar does not stop the run, but it is not
                    // waved through either: the reason goes to the log, and
                    // its gist to whoever is watching the command.
                    report($e);

                    $unreadable[] = 'User '.$user->id.': '.$user->avatar.' ('.$e->getMessage().')';
                }
            });

        foreach ($unreadable as $line) {
            $this->warn('Left alone, could not be read. '.$line);
        }

        if (! $this->option('commit')) {
            $this->warn($resized.' avatar(s) would be resized, '.$unchanged.' already fit. Re-run with --commit to resize.');

            return self::SUCCESS;
        }

        $this->info('Resized '.$resized.' avatar(s), '.$unchanged.' already fit.');

        if ($resized > 0) {
            $this->info('Changes noted in '.Storage::disk('local')->path($log));
        }

        return self::SUCCESS;
    }

    /**
     * Point the account at a smaller copy. Quietly, and without touching
     * updated_at: nobody has edited this account.
     */
    private function replace(User $user, UserAvatar $avatars, string $log): void
    {
        $original = $user->avatar;

        $user->avatar = $avatars->storeSmallerCopy($original);
        $user->timestamps = false;
        $user->saveQuietly();

        Storage::disk('local')->append($log, (string) json_encode([
            'user' => $user->id,
            'from' => $original,
            'to' => $user->avatar,
        ]));
    }
}
