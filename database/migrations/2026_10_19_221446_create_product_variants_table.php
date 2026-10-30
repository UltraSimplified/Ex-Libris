<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The thing actually bought. A print or a record has one, with neither
     * a colour nor a size; a t-shirt has one for each colour and size it
     * comes in. Price is in pence. Stock left empty means there is no limit,
     * as for something printed to order; nought means sold out.
     *
     * A colour and size appear once per product. Soft deleted, so an order
     * can still say which one was bought after the office stops selling it.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('colour')->nullable();
            $table->string('colour_hex', 7)->nullable();
            $table->string('size', 10)->nullable();
            $table->string('sku')->nullable()->unique();
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'colour', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
