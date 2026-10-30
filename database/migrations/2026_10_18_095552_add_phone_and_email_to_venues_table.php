<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How to get hold of a venue, shown under its website on its page of
     * listings.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('url');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email']);
        });
    }
};
