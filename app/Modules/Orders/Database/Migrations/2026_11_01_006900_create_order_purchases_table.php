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
        Schema::create('order_purchases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index('status');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_purchases');
    }
};
