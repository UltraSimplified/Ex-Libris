<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A gig widget: one embed of a venue's or a promoter's events on
     * somebody else's website. The key is what the snippet carries, so it
     * is random rather than the id and can be changed without renumbering.
     */
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('key', 24)->unique();
            $table->morphs('owner');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('template', 32)->default('list');
            $table->string('density', 32)->default('detailed');
            $table->json('options')->nullable();
            $table->json('colours')->nullable();
            $table->string('font', 32)->default('sans');
            $table->string('size_mode', 16)->default('auto');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
