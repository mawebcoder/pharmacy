<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders (roadmap D3.4, D3.5, S23). An order is created only when the customer goes to pay; it keeps a copy of
 * everything that can change later (address, customer, coupon, shipping quote, and each item's names and prices),
 * so editing the catalog or an address never changes a past order. Every status change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->string('status', 16);
            $table->string('payment_status', 24);
            $table->string('sourcing_status', 24)->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->json('address_snapshot');
            $table->json('customer_snapshot');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_price')->default(0);
            $table->unsignedBigInteger('shipping_discount')->default(0);
            $table->string('shipping_discount_reason', 16)->nullable();
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code', 32)->nullable();
            $table->json('coupon_snapshot')->nullable();
            $table->string('shipping_method', 16);
            $table->unsignedBigInteger('carrier_service_id')->nullable();
            $table->json('shipping_quote_snapshot');
            $table->unsignedBigInteger('payment_fee')->default(0);
            $table->unsignedBigInteger('shipping_cost_quoted')->nullable();
            $table->unsignedBigInteger('shipping_cost_actual')->nullable();
            $table->string('tracking_code', 64)->nullable();
            $table->unsignedBigInteger('delivery_slot_id')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reference', 64)->nullable();
            $table->unsignedBigInteger('refund_items_amount')->nullable();
            $table->unsignedBigInteger('refund_shipping_amount')->nullable();
            $table->timestamps();

            $table->unique('number');
            $table->index(['customer_id', 'placed_at']);
            $table->index(['status', 'expires_at']);
            $table->index('sourcing_status');
            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
