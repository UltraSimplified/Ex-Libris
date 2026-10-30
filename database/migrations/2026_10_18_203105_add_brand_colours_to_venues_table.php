<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same four colours a promoter already has, so a venue's gig
     * widgets can wear its own colours.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('brand_colour')->nullable();
            $table->string('accent_colour1')->nullable();
            $table->string('accent_colour2')->nullable();
            $table->string('brand_background_colour')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['brand_colour', 'accent_colour1', 'accent_colour2', 'brand_background_colour']);
        });
    }
};
