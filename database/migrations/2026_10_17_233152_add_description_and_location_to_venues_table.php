<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A venue's own description, shown under its cover photo on its page of
     * listings, and where it is, for the map beside its address. The place
     * is worked out from the postcode when the venue is saved, and can be
     * set by hand in the Office to put the pin exactly on the door.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->text('description')->nullable()->after('cover_photo');
            $table->decimal('latitude', 10, 7)->nullable()->after('postcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['description', 'latitude', 'longitude']);
        });
    }
};
