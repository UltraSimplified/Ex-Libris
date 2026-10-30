<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Features are put in order by hand in the Office, the highest number at
     * the top. The ones already written are numbered by when they were
     * made, so the list starts out as it was, newest first.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('featured')->index();
        });

        DB::table('features')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id, int $index) => DB::table('features')
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
