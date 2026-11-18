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
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32);
            $table->string('type', 16);
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->boolean('applies_to_sale_items')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
