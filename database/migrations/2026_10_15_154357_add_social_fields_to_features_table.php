<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a feature shows when it is shared: its own title, description and
     * image for Facebook, X, WhatsApp and the rest. All optional; a feature
     * without them is shared with its title, excerpt and cover photo.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->string('social_title')->nullable()->after('excerpt');
            $table->text('social_description')->nullable()->after('social_title');
            $table->string('social_image')->nullable()->after('social_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn(['social_title', 'social_description', 'social_image']);
        });
    }
};
