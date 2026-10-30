<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add visitor location columns to the advert events telemetry table.
     *
     * Raw city/region/country are stored alongside the computed locality
     * bucket so that bucket definitions can be revised and historic events
     * re-bucketed later. No IP address is persisted.
     */
    public function up(): void
    {
        Schema::table('advert_events', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('locality_bucket', 50)->nullable()->index();
        });
    }

    /**
     * Remove the visitor location columns.
     */
    public function down(): void
    {
        Schema::table('advert_events', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'region', 'city', 'locality_bucket']);
        });
    }
};
