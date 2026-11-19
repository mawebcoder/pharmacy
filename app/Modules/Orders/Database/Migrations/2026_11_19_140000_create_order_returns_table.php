<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order returns («مرجوعی‌ها», roadmap S27, owner 2026-11-19): an order may have several, each over the units no other
 * open return holds. A draft holds the items, their refund and Tipax's price for the way back; approving registers
 * the pickup with Tipax; cancelling an approved one cancels it at Tipax too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 16)->default('draft');
            $table->boolean('is_full')->default(false);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('items_refund')->default(0);
            $table->unsignedBigInteger('shipping_fee_refund')->default(0);
            $table->unsignedBigInteger('return_shipping_price')->nullable();
            $table->unsignedInteger('return_service_id')->nullable();
            $table->string('return_service_title', 100)->nullable();
            $table->json('parcels')->nullable();
            $table->json('tracking_codes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index('status');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
