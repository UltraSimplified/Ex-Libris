<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether a writer is still writing for the magazine. An active writer
     * is listed on Our Writers; one who is not becomes an alumnus, kept
     * for their bylines and their own page but off the masthead. Every
     * writer starts active, so the masthead is unchanged until the office
     * says otherwise.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active_writer')->default(true)->after('is_writer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active_writer');
        });
    }
};
