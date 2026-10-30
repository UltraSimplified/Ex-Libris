<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The dates over a listing are now worked out from its start and end,
     * so this field no longer describes them. It holds what those two days
     * cannot say, such as "Tuesday and Thursday mornings only", shown in
     * brackets after the dates.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->renameColumn('dates_description', 'additional_date_information');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->renameColumn('additional_date_information', 'dates_description');
        });
    }
};
