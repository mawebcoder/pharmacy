<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Most units of one SKU of this product in one order (S19, owner 2026-11-14): the SKU's own limit wins, then this,
 * then the store setting «حداکثر تعداد هر کالا در یک سفارش».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_per_order')->nullable()->after('requires_prescription');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('max_per_order');
        });
    }
};
