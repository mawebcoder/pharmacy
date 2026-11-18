<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flash deals («پیشنهاد لحظه‌ای», roadmap S28, D3.6): a SKU at a deal price between two times, optionally for a limited
 * number of units. While a deal is live and not sold out, its price is copied onto the SKU (flash_deal_price,
 * flash_deal_id) by SyncFlashDealPrices, so cards, search, cart and checkout use it; the pricing engine and the haraj
 * price are untouched (owner, 2026-11-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_deals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('deal_price');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_deals');
    }
};
