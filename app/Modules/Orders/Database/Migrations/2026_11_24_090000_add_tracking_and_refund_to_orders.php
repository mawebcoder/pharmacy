<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order admin (roadmap S29, owner 2026-11-15): the carrier's tracking code typed by the admin when the order goes on
 * its way (until Tipax tracking, S27), and the manual refund's date and bank or panel reference. An order that expired
 * unpaid now has the payment status «expired» (was «unpaid»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('tracking_code', 64)->nullable()->after('shipping_cost_actual');
            $table->timestamp('refunded_at')->nullable()->after('delivered_at');
            $table->string('refund_reference', 64)->nullable()->after('refunded_at');
        });

        DB::table('orders')->where('payment_status', 'unpaid')->update(['payment_status' => 'expired']);
    }

    public function down(): void
    {
        DB::table('orders')->where('payment_status', 'expired')->update(['payment_status' => 'unpaid']);

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['tracking_code', 'refunded_at', 'refund_reference']);
        });
    }
};
