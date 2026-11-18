<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carts (roadmap D3.4, D13.3, S21). A guest's cart is found by the SHA-256 hash of the random token in their
 * encrypted `cart_token` cookie (the token itself is never stored); a customer's cart by customer_id. Coupon, address
 * and shipping quote columns come with S22, S23 and S31.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id']);
            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
