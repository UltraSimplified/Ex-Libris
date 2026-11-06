<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A publisher: a friendly website trusted by the office to make gig
     * widgets of the events it has bookmarked, without running a venue or
     * a promoter. Granted in the Office, like the other roles.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_publisher')->default(false)->after('is_writer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_publisher');
        });
    }
};
