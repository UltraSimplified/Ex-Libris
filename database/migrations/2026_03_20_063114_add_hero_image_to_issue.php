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
        Schema::table('issues', function (Blueprint $table) {
            // UP
            $table->text('alt_text')->after('cover_photo')->nullable();
            $table->string('hero_image')->after('cover_photo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // DOWN
            $table->dropColumn('hero_image');
            $table->dropColumn('alt_text');
        });
    }
};
