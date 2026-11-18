<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment attempts (roadmap D7.4, S23): one link per attempt at the gateway, at most one pending (not expired) per
 * order at a time; it ends paid, failed or expired. Card numbers are never stored in full.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            // One paid payment per order: set only while the payment is paid, and unique.
            $table->unsignedBigInteger('paid_order_id')->nullable()->virtualAs("IF(`status` = 'paid', `order_id`, NULL)");
            $table->string('gateway', 16);
            $table->unsignedBigInteger('amount');
            $table->string('authority', 64);
            $table->string('url', 500);
            $table->string('status', 16);
            $table->timestamp('expires_at');
            $table->string('ref_id', 64)->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique('authority');
            $table->unique('paid_order_id');
            $table->index(['order_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
