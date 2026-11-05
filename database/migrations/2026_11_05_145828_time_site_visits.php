<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visits recorded before this knew only which hour they fell in, so they
     * stay null and the average passes over them rather than reading as
     * nothing at all.
     */
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dateTime('first_seen_at')->nullable()->after('requests');
            $table->dateTime('last_seen_at')->nullable()->after('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn(['first_seen_at', 'last_seen_at']);
        });
    }
};
