<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A fifth postage band, for donations and anything else that is never
     * posted or collected. It costs nothing, and needs_delivery being off
     * means a basket holding nothing else is neither posted nor collected:
     * the customer is not asked how they would like it, nor for a delivery
     * address. Listed first, as the cheapest.
     */
    public function up(): void
    {
        Schema::table('postage_bands', function (Blueprint $table) {
            $table->boolean('needs_delivery')->default(true)->after('amount');
        });

        $now = now();

        DB::table('postage_bands')->insert([
            'name' => 'No postage',
            'amount' => 0,
            'needs_delivery' => false,
            'description' => 'Donations, and anything else that is never posted or collected.',
            'position' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * Fails while any product is still in the band, rather than leaving
     * those products with no band and so taking them out of the shop.
     */
    public function down(): void
    {
        DB::table('postage_bands')->where('needs_delivery', false)->delete();

        Schema::table('postage_bands', function (Blueprint $table) {
            $table->dropColumn('needs_delivery');
        });
    }
};
