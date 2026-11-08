<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The days a listing is on, when it is not on every day of its run: one
     * row per day. A listing with no rows here is on every day from its
     * start date to its end date.
     */
    public function up(): void
    {
        Schema::create('listing_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unique(['listing_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_days');
    }
};
