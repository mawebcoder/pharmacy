<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons (roadmap D3.4, S20): the code and its rules, include lists of categories (with their subcategories),
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
            $table->string('code', 32)->unique();
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
        });

        foreach (['categories' => 'category_id', 'brands' => 'brand_id', 'products' => 'product_id'] as $target => $column) {
            Schema::create('coupon_'.$target, function (Blueprint $table) use ($target, $column): void {
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId($column)->constrained($target)->cascadeOnDelete();

                $table->primary(['coupon_id', $column]);
            });
        }

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('discount_amount');
            $table->string('status', 16);
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'customer_id']);
            $table->index(['status', 'reserved_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_brands');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupons');
    }
};
