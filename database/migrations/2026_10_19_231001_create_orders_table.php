<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An order from the shop. It is made, Pending, the moment the customer
     * is sent to Stripe, and Stripe's webhook says when it is paid. Who
     * paid, and the addresses they gave Stripe, arrive with that webhook,
     * so they are empty until then - a guest is unknown until they have
     * paid.
     *
     * Money is in pence. The addresses are copies of what was given at the
     * time, not links to an account's addresses, so a later change of
     * address never rewrites where an old parcel went. An order outlives
     * the account that placed it.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('status', 20)->index();
            $table->string('fulfilment', 20);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping');
            $table->unsignedInteger('total');
            $table->boolean('postage_capped')->default(false);
            $table->char('currency', 3)->default('gbp');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->text('receipt_url')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->boolean('oversold')->default(false);
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
