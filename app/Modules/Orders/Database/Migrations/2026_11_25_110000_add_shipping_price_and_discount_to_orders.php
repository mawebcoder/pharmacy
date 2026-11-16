<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every order keeps the shipping price before any discount and the shipping discount with its reason (a free-shipping
 * coupon or the order-amount threshold), owner 2026-11-16, so a cancellation after delivery can hold back the free
 * shipping the store paid for. shipping_total stays what the customer was charged: shipping_price − shipping_discount.
 * Existing orders are filled from their shipping quote snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipping_price')->default(0)->after('discount_total');
            $table->unsignedBigInteger('shipping_discount')->default(0)->after('shipping_price');
            $table->string('shipping_discount_reason', 16)->nullable()->after('shipping_discount');
        });

        DB::table('orders')->orderBy('id')->chunkById(500, function ($orders): void {
            foreach ($orders as $order) {
                $quote = json_decode((string) $order->shipping_quote_snapshot, true) ?: [];
                $free = (bool) ($quote['free'] ?? false);
                $price = $free ? (int) ($quote['flat_rate'] ?? 0) : (int) $order->shipping_total;

                DB::table('orders')->where('id', $order->id)->update([
                    'shipping_price' => max($price, (int) $order->shipping_total),
                    'shipping_discount' => max(0, $price - (int) $order->shipping_total),
                    'shipping_discount_reason' => $free && $price > (int) $order->shipping_total ? ($quote['free_reason'] ?? null) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['shipping_price', 'shipping_discount', 'shipping_discount_reason']);
        });
    }
};
