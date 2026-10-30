<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The four postage bands and the shop's settings row, which the shop
     * cannot work without and the Office only edits, never creates. The
     * amounts are placeholders, to be set to real Royal Mail prices in the
     * Office before the shop opens.
     */
    public function up(): void
    {
        $now = now();

        DB::table('postage_bands')->insert([
            ['name' => 'Letter', 'amount' => 200, 'description' => 'Small prints, in a board-backed envelope.', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Large letter', 'amount' => 300, 'description' => 'T-shirts, in a padded mailer.', 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Parcel', 'amount' => 400, 'description' => 'Records, in a rigid LP mailer.', 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tube', 'amount' => 600, 'description' => 'Posters and large prints, rolled in a postal tube.', 'position' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('shop_settings')->insert([
            'postage_cap' => 1000,
            'collection_name' => 'Good on Paper, Centre for Science & Art',
            'collection_address' => "Lansdown\nStroud\nGL5 1BB",
            'collection_hours' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('shop_settings')->delete();
        DB::table('postage_bands')->delete();
    }
};
