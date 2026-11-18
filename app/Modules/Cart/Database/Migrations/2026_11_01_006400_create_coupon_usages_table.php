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
        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('discount_amount');
            $table->string('status', 16);
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'customer_id']);
            $table->index(['status', 'reserved_until']);
            $table->index('order_id');
            $table->foreign('coupon_id')->references('id')->on('coupons')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
