<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hold a listing back when nobody vouches for whoever sent it.
     *
     * Anyone may add a listing - a pub quiz, a village fete, a school
     * fundraiser - but a submission from an account managing neither a
     * promoter nor a venue waits for the office before it goes public, and
     * carries a phone number so it can be followed up.
     *
     * Defaults to false so that everything already listed stays listed.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('awaiting_approval')->default(false)->after('boosted');
            $table->string('contact_phone')->nullable()->after('awaiting_approval');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn(['awaiting_approval', 'contact_phone']);
        });
    }
};
