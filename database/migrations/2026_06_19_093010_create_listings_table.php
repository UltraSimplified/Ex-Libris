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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->string('cover_photo')->nullable();
            $table->string('thumbnail')->nullable();
            $table->longText('body');
            $table->string('event_link')->nullable();
            $table->json('video_playlist')->nullable();
            $table->json('gallery')->nullable();

            $table->unsignedBigInteger('venue_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();

            $table->boolean('featured')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('dates_description')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('venue_id')->references('id')->on('venues');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
