<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A feature can be picked for the top of the home page. Nothing is
     * picked to begin with, and the home page shows the newest feature
     * there until something is.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('featured');
        });
    }
};
