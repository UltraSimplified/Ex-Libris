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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->integer('issue_number')->nullable();
            $table->text('month_year')->nullable();
            $table->text('tagline')->nullable();
            $table->string('cover_photo')->nullable();
            $table->Text('cover_artist')->nullable();
            $table->mediumText('playlist')->nullable();
            $table->text('playlist_creator')->nullable();
            $table->string('pdf')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
