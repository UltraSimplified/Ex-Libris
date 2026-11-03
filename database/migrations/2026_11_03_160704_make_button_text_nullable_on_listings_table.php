<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A listing with nothing to sell has nothing to put on a button, so
     * leaving the wording out now means no button at all rather than an
     * empty one. The column's old default fired only when the column was
     * left out of the insert, which the forms never do.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('button_text')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('listings')->whereNull('button_text')->update(['button_text' => 'Buy tickets']);

        Schema::table('listings', function (Blueprint $table) {
            $table->string('button_text')->default('Buy tickets')->change();
        });
    }
};
