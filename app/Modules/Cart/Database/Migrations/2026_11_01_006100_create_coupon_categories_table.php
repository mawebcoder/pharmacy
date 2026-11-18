<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons (roadmap D3.4, S22): the code and its rules, include lists of categories (with their subcategories),
 * brands and products, and usages. A usage is reserved when the payment link is created, marked used when the
 * payment is verified, or released when it fails or expires (order_id gets its foreign key with the orders table).
 * Checkout works the discount out on the fly, so carts store no coupon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('category_id');

            $table->primary(['coupon_id', 'category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_categories');
    }
};
