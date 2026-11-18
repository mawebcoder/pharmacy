<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * variant_prices.variant_id → product_variants: added after product_variants exists, since that table refers back to variant_prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->dropForeign('variant_prices_variant_id_foreign');
        });
    }
};
