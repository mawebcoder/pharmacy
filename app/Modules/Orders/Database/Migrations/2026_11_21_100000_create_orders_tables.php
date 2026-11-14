<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders (roadmap D3.4, D3.5, S25). An order is created only when the customer goes to pay; it keeps a copy of
 * everything that can change later (address, customer, coupon, shipping quote, and each item's names and prices),
 * so editing the catalog or an address never changes a past order. Every status change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 16);
            $table->string('payment_status', 24);
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('address_snapshot');
            $table->json('customer_snapshot');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('total');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 32)->nullable();
            $table->json('coupon_snapshot')->nullable();
            $table->string('shipping_method', 16);
            $table->unsignedBigInteger('carrier_service_id')->nullable();
            $table->json('shipping_quote_snapshot');
            $table->unsignedBigInteger('payment_fee')->default(0);
            $table->unsignedBigInteger('shipping_cost_quoted')->nullable();
            $table->unsignedBigInteger('shipping_cost_actual')->nullable();
            $table->unsignedBigInteger('delivery_slot_id')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'placed_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->foreignId('variant_price_id')->nullable()->constrained('variant_prices')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_title')->nullable();
            $table->string('sku', 64);
            $table->string('brand_name')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('product_type', 32)->nullable();
            $table->boolean('requires_prescription')->default(false);
            $table->unsignedSmallInteger('quantity');
            $table->json('product_snapshot');
            $table->unsignedBigInteger('provider_id')->nullable()->index();
            $table->string('provider_name')->nullable();
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->string('commission_source', 24)->nullable();
            $table->string('provider_choice', 24)->nullable();
            $table->unsignedBigInteger('official_price')->nullable();
            $table->unsignedBigInteger('list_price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('coupon_usages')) {
            Schema::table('coupon_usages', function (Blueprint $table): void {
                $table->dropForeign(['order_id']);
            });
        }

        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
