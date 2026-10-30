<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A variant on sale now carries the lower price it sells for, rather
     * than a higher price it used to sell for. Below the price, the sale
     * price is what is charged, with the price shown struck through after
     * it. The one variant with a value had been given the lower price
     * already, so its value carries straight across.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->renameColumn('was_price', 'sale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->renameColumn('sale_price', 'was_price');
        });
    }
};
