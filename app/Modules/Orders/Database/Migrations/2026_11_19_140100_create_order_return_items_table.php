<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The units of order items a return takes back (roadmap S27, owner 2026-11-19), with what the customer paid per unit
 * (copied from the order item) and the refund worked out from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_return_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_return_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('paid_unit');
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->timestamps();

            $table->unique(['order_return_id', 'order_item_id']);
            $table->foreign('order_return_id')->references('id')->on('order_returns')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
    }
};
