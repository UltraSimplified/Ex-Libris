<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a page shows when it is shared, as a feature has: its own title,
     * description and image. All optional; a page without them is shared
     * with its title, the opening of its text and its hero image.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('social_title')->nullable()->after('hero_image');
            $table->text('social_description')->nullable()->after('social_title');
            $table->string('social_image')->nullable()->after('social_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['social_title', 'social_description', 'social_image']);
        });
    }
};
