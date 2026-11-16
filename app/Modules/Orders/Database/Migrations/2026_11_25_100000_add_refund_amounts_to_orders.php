<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «ثبت بازگشت وجه» records how much was paid back for the products and for shipping (owner, 2026-11-16), each at most
 * what the customer paid for it. Refunds recorded before this were full refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('refund_items_amount')->nullable()->after('refund_reference');
            $table->unsignedBigInteger('refund_shipping_amount')->nullable()->after('refund_items_amount');
        });

        DB::table('orders')->whereNotNull('refunded_at')->update([
            'refund_items_amount' => DB::raw('subtotal - discount_total'),
            'refund_shipping_amount' => DB::raw('shipping_total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refund_items_amount', 'refund_shipping_amount']);
        });
    }
};
