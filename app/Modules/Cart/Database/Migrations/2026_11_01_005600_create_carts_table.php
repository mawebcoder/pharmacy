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
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('session_token_hash', 64)->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
            $table->unique('session_token_hash');
            $table->index('expires_at');
            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
