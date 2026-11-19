<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping prices for when Tipax cannot price an order (roadmap S31 «fallback RateTable», owner 2026-11-19): one row
 * per weight band, a price (rial) per distance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_fallback_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('max_weight_g');
            $table->unsignedBigInteger('same_city_price');
            $table->unsignedBigInteger('same_province_price');
            $table->unsignedBigInteger('other_province_price');
            $table->timestamps();

            $table->unique('max_weight_g');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_fallback_rates');
    }
};
