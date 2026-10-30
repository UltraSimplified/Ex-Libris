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
        Schema::dropIfExists('articles');
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->integer('issue_number')->nullable();
            $table->string('title')->sortable();
            $table->date('published')->nullable();
            $table->string('slug')->nullable();
            $table->enum('colourway', ['white', 'hi-vis', 'slate', 'key-lime'])->default('white');
            $table->foreignId('tag_id')->nullable();
            $table->string('cover_photo')->nullable();
            $table->text('cover_alt_text')->nullable();
            $table->string('feature_image')->nullable();
            $table->text('feature_alt_text')->nullable();
            $table->longText('body');
            $table->text('excerpt')->nullable();
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->foreignId('writer_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
