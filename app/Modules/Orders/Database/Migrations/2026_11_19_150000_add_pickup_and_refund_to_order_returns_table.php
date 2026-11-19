<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A return's way back and its refund (roadmap S27, owner 2026-11-19): whether Tipax collects it at all (items never
 * sent need no pickup), Tipax's last status and stage, when the parcels were picked up and reached the warehouse, and
 * the refund (only after pickup when there is one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->boolean('needs_pickup')->default(true)->after('is_full');
            $table->string('tipax_status', 255)->nullable()->after('tracking_codes');
            $table->string('tipax_stage', 16)->nullable()->after('tipax_status');
            $table->timestamp('tipax_checked_at')->nullable()->after('tipax_stage');
            $table->timestamp('picked_up_at')->nullable()->after('tipax_checked_at');
            $table->timestamp('received_at')->nullable()->after('picked_up_at');
            $table->timestamp('refunded_at')->nullable()->after('received_at');
            $table->unsignedBigInteger('refunded_by')->nullable()->after('refunded_at');
            $table->string('refund_reference', 100)->nullable()->after('refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropColumn(['needs_pickup', 'tipax_status', 'tipax_stage', 'tipax_checked_at', 'picked_up_at', 'received_at', 'refunded_at', 'refunded_by', 'refund_reference']);
        });
    }
};
