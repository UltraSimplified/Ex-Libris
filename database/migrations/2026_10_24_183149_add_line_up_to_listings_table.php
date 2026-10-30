<?php

use App\Services\Listings\ListingLineUp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A listing's single audio playlist and single video become a line-up:
     * the artists on the bill, each with a playlist, a video or both.
     *
     * Each listing's existing pair is carried over as a one-artist line-up.
     * An artist needs a name and nobody has ever entered one, so the
     * listing's title stands in until an editor corrects it. Both embeds go
     * through the sanitiser on the way, so an old Office paste that fails
     * comes across as nothing rather than as raw markup.
     *
     * The old columns stay, unread, until a later release drops them.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->json('line_up')->nullable()->after('video_embed');
        });

        $lineUps = app(ListingLineUp::class);

        DB::table('listings')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereNotNull('audio_playlist')->where('audio_playlist', '!=', ''))
                ->orWhere(fn ($query) => $query->whereNotNull('video_embed')->where('video_embed', '!=', '')))
            ->orderBy('id')
            ->each(function (object $listing) use ($lineUps): void {
                $lineUp = $lineUps->fromSingleEmbeds(
                    (string) $listing->title,
                    $listing->audio_playlist,
                    $listing->video_embed,
                );

                if ($lineUp !== null) {
                    DB::table('listings')->where('id', $listing->id)->update(['line_up' => $lineUp]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * The old columns were never changed, so dropping the line-up is enough.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('line_up');
        });
    }
};
