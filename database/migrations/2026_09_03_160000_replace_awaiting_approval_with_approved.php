<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Turns awaiting_approval round into approved, which can say three
     * things rather than two:
     *
     *   null  the listing was entered or imported by the office, so
     *         approval never applied to it
     *   0     submitted from the dashboard and still to be read
     *   1     submitted and let through
     *
     * The old column could only ever say "held back or not", which left
     * the 612 listings the office typed in indistinguishable from the
     * handful somebody here had actually read and released.
     *
     * Nothing is pending at the time of writing - checked against the
     * table, not assumed - so no row's held-back state has to survive the
     * change. Anything with an account behind it went through the queue
     * and came out the other side; everything else is the office's own.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('approved')->nullable()->after('boosted');
        });

        // Left null for the office's own listings, which is the default.
        DB::table('listings')
            ->whereNotNull('user_id')
            ->update(['approved' => DB::raw('CASE WHEN awaiting_approval = 1 THEN 0 ELSE 1 END')]);

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('awaiting_approval');
        });
    }

    /**
     * Reverse the migrations.
     *
     * A listing the office entered goes back to 0, which is what it held
     * before and what the old column read as public.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('awaiting_approval')->default(false)->after('boosted');
        });

        DB::table('listings')
            ->update(['awaiting_approval' => DB::raw('CASE WHEN approved = 0 THEN 1 ELSE 0 END')]);

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('approved');
        });
    }
};
