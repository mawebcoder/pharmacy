<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The expiry date of the units a purchase document row bought (owner, 2026-11-16): recorded for reference only; the
 * store promises a minimum shelf life per product, not an exact date, so nothing checks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_purchase_items', function (Blueprint $table): void {
            $table->date('expiry_date')->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('order_purchase_items', function (Blueprint $table): void {
            $table->dropColumn('expiry_date');
        });
    }
};
