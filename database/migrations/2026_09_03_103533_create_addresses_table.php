<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An address is a row of its own rather than more columns on the user,
     * so that an account can hold the two it needs now and an order can
     * later keep a copy of where it was actually sent.
     *
     * The type is unique per account: one billing address, one delivery
     * address, and no way for a second of either to appear behind the
     * form's back. Nothing is required beyond the account it belongs to,
     * because the shape of a correct address is a matter for validation
     * and differs by country.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('line_one')->nullable();
            $table->string('line_two')->nullable();
            $table->string('town')->nullable();
            $table->string('county')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
