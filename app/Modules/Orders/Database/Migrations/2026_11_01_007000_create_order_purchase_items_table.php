<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase documents (roadmap S25, owner 2026-11-16; replaces the per-supplier provider_purchases of D3.4): one per
 * order, opened by an admin, whose rows say which supplier provided how many units of an order item at what cost. The
 * system never creates rows or costs. The order's sourcing status (admin only) follows the document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_purchase_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_name', 255);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->string('missing_note', 500)->nullable();
            $table->timestamps();

            $table->index('provider_id');
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('order_purchase_id')->references('id')->on('order_purchases')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_purchase_items');
    }
};
