<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A promoter's about was a 255-character string, while the dashboard
     * lets an owner write up to 2,000 and the Office set no limit, so a
     * paragraph or two could not be saved from either.
     */
    public function up(): void
    {
        Schema::table('promoters', function (Blueprint $table) {
            $table->text('about')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Only safe while no about runs past 255 characters.
     */
    public function down(): void
    {
        Schema::table('promoters', function (Blueprint $table) {
            $table->string('about')->nullable()->change();
        });
    }
};
