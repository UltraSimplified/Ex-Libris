<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether somebody who saved an event is sorted for it. The Dashboard
     * calls that "Tickets purchased", or "Going" for a free event: two
     * labels for the same tick, so the column is named for neither.
     */
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->boolean('attending')->default(false)->after('listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->dropColumn('attending');
        });
    }
};
