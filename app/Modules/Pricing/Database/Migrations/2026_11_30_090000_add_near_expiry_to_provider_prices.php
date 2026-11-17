<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «زودمصرف» per supplier (owner, 2026-11-17): each supplier's stock of a SKU has its own expiry, so a supplier row can
 * be marked near expiry with that stock's expiry date (required when marked). The price record keeps the selling
 * supplier's flag and date of that moment, so orders keep what was sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_prices', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->after('is_preferred');
            $table->date('expiry_date')->nullable()->after('is_near_expiry');
        });

        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->after('sale_price');
            $table->date('expiry_date')->nullable()->after('is_near_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->dropColumn(['is_near_expiry', 'expiry_date']);
        });

        Schema::table('provider_prices', function (Blueprint $table): void {
            $table->dropColumn(['is_near_expiry', 'expiry_date']);
        });
    }
};
