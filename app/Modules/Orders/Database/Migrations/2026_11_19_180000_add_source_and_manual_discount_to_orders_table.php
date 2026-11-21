<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an order was placed (owner, 2026-11-19): on the site by the customer, or in the panel by staff (which admin),
 * and a discount staff typed in for a panel order, with its reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('source', 16)->default('site')->after('payment_status');
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->after('source');
            $table->unsignedBigInteger('manual_discount')->default(0)->after('discount_total');
            $table->string('manual_discount_reason', 500)->nullable()->after('manual_discount');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'created_by_admin_id', 'manual_discount', 'manual_discount_reason']);
        });
    }
};
