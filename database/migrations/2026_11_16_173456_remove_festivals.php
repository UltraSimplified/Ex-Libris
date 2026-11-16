<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Festivals were taken back out the day they went in, and the migration
     * that made room for them went with the code. It had already run on
     * staging and on live, so this clears away what it left there: the
     * festivals table, a listing's place in a festival and whether it wore
     * the festival's mark, and the Runs Festivals permission on an account.
     *
     * A database set up since never had any of it, so each step looks
     * before it drops.
     *
     * A widget made to show a festival's events goes too: its owner is a
     * class that no longer exists, and loading it would fail.
     */
    public function up(): void
    {
        DB::table('widgets')->where('owner_type', 'App\Models\Festival')->delete();

        if (Schema::hasColumn('users', 'runs_festivals')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('runs_festivals');
            });
        }

        if (Schema::hasColumn('listings', 'festival_id')) {
            Schema::table('listings', function (Blueprint $table): void {
                $table->dropForeign(['festival_id']);
                $table->dropColumn(['festival_id', 'show_festival_mark']);
            });
        }

        Schema::dropIfExists('festivals');
    }

    /**
     * There is nothing to go back to. The festivals themselves are gone,
     * and so is the code that read these tables, so putting the empty
     * tables back would restore nothing anybody could use.
     */
    public function down(): void
    {
        throw new RuntimeException('Removing festivals cannot be rolled back: the code that used their tables has gone.');
    }
};
