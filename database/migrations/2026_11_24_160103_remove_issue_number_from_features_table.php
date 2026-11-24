<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A feature no longer records the issue it was printed in. Features are
     * written for the site now rather than carried over from the magazine,
     * and nothing reads the number.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $table->dropColumn('issue_number');
        });
    }

    /**
     * Puts the column back empty. The numbers it held went when it was
     * dropped.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table): void {
            $table->integer('issue_number')->nullable()->after('id');
        });
    }
};
