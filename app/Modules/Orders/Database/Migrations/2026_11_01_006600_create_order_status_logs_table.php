<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders (roadmap D3.4, D3.5, S23). An order is created only when the customer goes to pay; it keeps a copy of
 * everything that can change later (address, customer, coupon, shipping quote, and each item's names and prices),
 * so editing the catalog or an address never changes a past order. Every status change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
