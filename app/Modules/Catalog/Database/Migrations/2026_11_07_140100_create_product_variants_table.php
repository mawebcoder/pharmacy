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
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('title_fa');
            $table->unsignedBigInteger('official_price')->nullable();   // Rial
            $table->unsignedBigInteger('price');                        // Rial
            $table->unsignedBigInteger('sale_price')->nullable();       // Rial
            $table->string('stock_status', 32)->default('available');
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_near_expiry')->default(false)->index();
            $table->boolean('is_damaged_box')->default(false)->index();
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

            $table->index(['is_active', 'stock_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
