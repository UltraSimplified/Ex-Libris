<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A sponsorship is an advert shown only in the panel at the foot of every
     * page; every other advert takes the slots among the listings. Adverts
     * already here are all of the second kind, so nothing changes until one
     * is ticked.
     *
     * Adverts are archived rather than deleted, so their events, and the
     * report made from them, outlive the run.
     *
     * The slot column goes: nothing ever set it, and which slot an advert
     * takes now follows from the flag.
     */
    public function up(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            $table->boolean('sponsorship')->default(false)->after('active');
            $table->softDeletes();
            $table->index(['sponsorship', 'active']);
            $table->dropColumn('slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            $table->string('slot')->after('run_to')->default('Primary panel');
            $table->dropIndex(['sponsorship', 'active']);
            $table->dropSoftDeletes();
            $table->dropColumn('sponsorship');
        });
    }
};
