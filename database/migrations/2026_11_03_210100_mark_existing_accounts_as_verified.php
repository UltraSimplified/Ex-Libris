<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stamped on every account this touches, so that down() can tell them
     * from accounts that verified for themselves.
     */
    private const VERIFIED_AT = '2026-11-03 00:00:00';

    /**
     * Accounts must now verify their address, and every account made before
     * that was never asked to, so without this they would all be locked out
     * of the dashboard. An account with no address cannot log in, so it is
     * left for when it is given one.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->update(['email_verified_at' => self::VERIFIED_AT]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('email_verified_at', self::VERIFIED_AT)
            ->update(['email_verified_at' => null]);
    }
};
