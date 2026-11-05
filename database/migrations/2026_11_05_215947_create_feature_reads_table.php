<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per feature per day, holding only totals: nothing here says
     * who read what. "reads" is reserved in MySQL, hence read_count.
     */
    public function up(): void
    {
        Schema::create('feature_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->date('read_on');
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('seconds_read')->default(0);
            $table->unsignedInteger('reached_end_count')->default(0);
            $table->unique(['feature_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_reads');
    }
};
