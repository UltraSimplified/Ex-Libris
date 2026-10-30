<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an account is allowed to be, granted by the office.
     *
     * Held apart from what an account already owns: somebody can be a
     * writer without a published article, or be trusted to run venues
     * before the first one is attached to them. Staff are all three
     * without being marked as any of them - see the User model.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('venue_mgr')->default(false)->after('phone');
            $table->boolean('promoter')->default(false)->after('venue_mgr');
            $table->boolean('writer')->default(false)->after('promoter');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['venue_mgr', 'promoter', 'writer']);
        });
    }
};
