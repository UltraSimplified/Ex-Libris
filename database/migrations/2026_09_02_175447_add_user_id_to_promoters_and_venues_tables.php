<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give a promoter and a venue an owner who can manage them.
     *
     * One column rather than a pivot: a promoter or a venue answers to one
     * account, though that account may hold several of each. Nullable
     * because the magazine's own records have nobody behind them, and
     * because an owner has to be granted in Nova rather than claimed.
     */
    public function up(): void
    {
        foreach (['promoters', 'venues'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['promoters', 'venues'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
