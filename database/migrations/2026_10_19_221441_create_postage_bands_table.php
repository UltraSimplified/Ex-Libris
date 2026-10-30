<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What it costs to post one of a kind of thing: a small print in a
     * letter, a t-shirt in a mailer, a record in a box, a poster in a tube.
     * Every product belongs to one band, and an order pays each item's band
     * up to the cap held in shop_settings. Amounts are in pence.
     */
    public function up(): void
    {
        Schema::create('postage_bands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postage_bands');
    }
};
