<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->string('ticket_price')->nullable()->after('video_embed');
            $table->string('event_time_description')->nullable()->after('end_date');
            $table->string('button_text')->default('Buy tickets')->after('ticket_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->dropColumn('button_text');
            $table->dropColumn('event_time_description');
            $table->dropColumn('ticket_price');
        });
    }
};
