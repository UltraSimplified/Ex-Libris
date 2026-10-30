<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an event has sold out, which the listing says across its
     * own image rather than in the body where it would be missed.
     *
     * Not nullable: an event either has sold out or it has not, and
     * everything already listed has not.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('sold_out')->default(false)->after('boosted');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn('sold_out');
        });
    }
};
