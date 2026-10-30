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
            $table->dropColumn('featured');
            $table->dropColumn('video_playlist');
            $table->text('audio_playlist')->nullable()->after('gallery');
            $table->text('video_embed')->nullable()->after('gallery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->dropColumn('video_embed');
            $table->dropColumn('audio_playlist');
            $table->json('video_playlist')->nullable();
            $table->boolean('featured')->default(0);
        });
    }
};
