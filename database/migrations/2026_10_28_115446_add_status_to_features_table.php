<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Features are drafts or published. Every feature already written is
     * on the site, so it is published; a new one starts as a draft in the
     * Office. A draft may be saved before it is finished, so the subject,
     * the one other thing a feature could not be saved without, may be
     * left empty until it is published.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('featured')->index();
            $table->string('subject')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
            $table->string('subject')->nullable(false)->change();
        });
    }
};
