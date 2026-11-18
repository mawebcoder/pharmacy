<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sellable unit of a product (roadmap D3.1, D12.3): prices in rial, weight in grams, sizes in millimetres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 64);
            $table->string('barcode', 64)->nullable();
            $table->string('title_fa', 255);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedBigInteger('official_price')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->decimal('haraj_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('flash_deal_price')->nullable();
            $table->unsignedBigInteger('flash_deal_id')->nullable();
            $table->boolean('is_near_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('current_price_id')->nullable();
            $table->string('stock_status', 32)->default('available');
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->boolean('dimensions_verified')->default(false);
            $table->boolean('ship_separately')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_liquid')->default(false);
            $table->boolean('requires_cold_chain')->default(false);
            $table->unsignedSmallInteger('max_per_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('sku');
            $table->index(['is_active', 'stock_status']);
            $table->index('barcode');
            $table->index(['product_id', 'sort']);
            $table->index('is_near_expiry');
            $table->foreign('current_price_id')->references('id')->on('variant_prices')->nullOnDelete();
            $table->foreign('flash_deal_id')->references('id')->on('flash_deals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
