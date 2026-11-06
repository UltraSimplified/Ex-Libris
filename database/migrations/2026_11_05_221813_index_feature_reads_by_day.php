<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Office's Features card reads the last 30 days of every feature,
     * which the unique index cannot serve: it leads with the feature. The
     * table keeps every day, so without this the card scans all of them.
     */
    public function up(): void
    {
        Schema::table('feature_reads', function (Blueprint $table) {
            $table->index('read_on');
        });
    }

    public function down(): void
    {
        Schema::table('feature_reads', function (Blueprint $table) {
            $table->dropIndex(['read_on']);
        });
    }
};
