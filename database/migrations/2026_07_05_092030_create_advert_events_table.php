<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advert_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                  // 'impression' | 'click'
            $table->string('page')->nullable();          // route/path where it was shown
            $table->string('variant', 10)->nullable();   // 'mobile' | 'desktop'
            $table->string('location')->nullable();      // 'Bristol / United Kingdom'
            $table->timestamp('created_at')->useCurrent();
            $table->index(['advert_id', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advert_events');
    }
};
