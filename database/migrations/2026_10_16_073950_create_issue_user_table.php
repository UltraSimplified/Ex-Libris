<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The issues of the magazine a writer has an article in.
     *
     * Most of what a writer has written was printed rather than put on the
     * site, so this is kept by hand in the Office rather than worked out
     * from their features. Both sides cascade: a writer or an issue that
     * goes takes its links with it. The pair is unique, so attaching an
     * issue twice is attaching it once.
     */
    public function up(): void
    {
        Schema::create('issue_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'issue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_user');
    }
};
