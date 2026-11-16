<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shelf-life guarantee per product (owner, 2026-11-16): goods are bought from the supplier after the order is
 * placed, so no exact expiry date is promised; the product page shows «تضمین اعتبار: حداقل N ماه». Months only; empty
 * shows nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedTinyInteger('min_shelf_life_months')->nullable()->after('max_per_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('min_shelf_life_months');
        });
    }
};
