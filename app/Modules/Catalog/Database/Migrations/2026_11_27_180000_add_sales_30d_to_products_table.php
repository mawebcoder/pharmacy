<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units sold in the last 30 days (roadmap S39): the popularity signal for search ranking and «پرفروش‌ترین», counted
 * nightly from paid orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('sales_30d')->default(0)->after('is_fragile');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('sales_30d');
        });
    }
};
