<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row of the shop-wide values the office changes from the Office
     * rather than by a deploy: the most an order pays for postage, and where
     * and when a collection order can be picked up.
     */
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('postage_cap');
            $table->string('collection_name');
            $table->text('collection_address');
            $table->text('collection_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
