<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages already counted stand at the one request the visit was recorded
     * from, which is all that was ever known about them.
     */
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->unsignedInteger('requests')->default(1)->after('visitor_hash');
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn('requests');
        });
    }
};
