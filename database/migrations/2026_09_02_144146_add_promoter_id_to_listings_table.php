<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attribute a listing to the promoter behind it.
     *
     * Nullable because every listing predating this column has no promoter to
     * point at, and because a venue's own events legitimately have none.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->foreignId('promoter_id')
                ->nullable()
                ->after('venue_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['promoter_id']);
            $table->dropColumn('promoter_id');
        });
    }
};
