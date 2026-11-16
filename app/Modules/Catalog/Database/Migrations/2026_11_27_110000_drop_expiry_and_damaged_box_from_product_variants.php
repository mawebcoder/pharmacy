<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKUs lose their expiry date and the «کج‌جعبه» flag (owner, 2026-11-16): there is no warehouse yet, goods are bought
 * after the order is placed and only sound goods are sold, so the store promises a minimum shelf life per product
 * instead, and «زودمصرف» is a flag the admin sets by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['is_damaged_box']);
            $table->dropColumn(['expiry_date', 'is_damaged_box']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_damaged_box')->default(false)->index();
        });
    }
};
