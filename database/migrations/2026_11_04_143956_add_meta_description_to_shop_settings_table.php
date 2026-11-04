<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the shop page describes itself to search engines and link previews,
     * so the office can change it in the Office. The one row starts with some
     * words already in the box.
     */
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->text('meta_description')->nullable()->after('collection_hours');
        });

        DB::table('shop_settings')->update([
            'meta_description' => 'Prints, posters, t-shirts and records from Good on Paper, your friendly neighbourhood magazine.',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('meta_description');
        });
    }
};
