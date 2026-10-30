<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per widget per day rather than a row per view, so counting
     * stays a single update however busy the host site is.
     */
    public function up(): void
    {
        Schema::create('widget_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['widget_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_daily_stats');
    }
};
