<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Listings are no longer held back for the office to read: only the
     * office and the accounts it has linked to a promoter or venue add them,
     * and what they save goes straight up. So the approval flag, and the
     * phone number held-back submitters had to give, both go. Nothing was
     * waiting when this was written (24 October 2026).
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['approved', 'contact_phone']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The columns come back empty: what they held is not recoverable.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('approved')->nullable()->after('boosted');
            $table->string('contact_phone')->nullable()->after('approved');
        });
    }
};
