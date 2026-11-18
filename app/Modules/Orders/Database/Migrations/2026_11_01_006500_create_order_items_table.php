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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_price_id')->nullable();
            $table->unsignedBigInteger('flash_deal_id')->nullable();
            $table->string('product_name', 255);
            $table->string('variant_title', 255)->nullable();
            $table->string('sku', 64);
            $table->string('brand_name', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('product_type', 32)->nullable();
            $table->boolean('requires_prescription')->default(false);
            $table->unsignedSmallInteger('quantity');
            $table->json('product_snapshot');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->string('commission_source', 24)->nullable();
            $table->string('provider_choice', 24)->nullable();
            $table->unsignedBigInteger('official_price')->nullable();
            $table->unsignedBigInteger('list_price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('haraj_discount_unit')->default(0);
            $table->unsignedBigInteger('haraj_discount_total')->default(0);
            $table->unsignedBigInteger('coupon_discount_unit')->default(0);
            $table->unsignedBigInteger('coupon_discount_total')->default(0);
            $table->unsignedBigInteger('paid_unit')->default(0);
            $table->unsignedBigInteger('paid_total');
            $table->timestamps();

            $table->index('product_id');
            $table->index('provider_id');
            $table->foreign('flash_deal_id')->references('id')->on('flash_deals')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('variant_price_id')->references('id')->on('variant_prices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
