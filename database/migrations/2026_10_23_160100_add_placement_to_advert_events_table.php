<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where on the page an impression or click happened: 'panel', the
     * sponsorship panel at the foot of the page, or 'list', a slot among the
     * listings. Events from before this was recorded are left null.
     */
    public function up(): void
    {
        Schema::table('advert_events', function (Blueprint $table) {
            $table->string('placement', 10)->nullable()->after('variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advert_events', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
