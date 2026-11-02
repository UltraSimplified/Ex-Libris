<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->renameColumn('visited_on', 'visited_at');
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $table->dateTime('visited_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $firstVisitOfEachDay = DB::table('site_visits')
            ->selectRaw('min(id) as id')
            ->groupByRaw('date(visited_at), visitor_hash')
            ->pluck('id');

        DB::table('site_visits')->whereNotIn('id', $firstVisitOfEachDay)->delete();

        Schema::table('site_visits', function (Blueprint $table) {
            $table->date('visited_at')->change();
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $table->renameColumn('visited_at', 'visited_on');
        });
    }
};
