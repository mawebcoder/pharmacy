<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flash deals («پیشنهاد لحظه‌ای», roadmap S33, D3.6): a SKU at a deal price between two times, optionally for a limited
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
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('deal_price');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('flash_deal_price')->nullable()->after('sale_price');
            $table->foreignId('flash_deal_id')->nullable()->after('flash_deal_price')->constrained('flash_deals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('flash_deal_id');
            $table->dropColumn('flash_deal_price');
        });

        Schema::dropIfExists('flash_deals');
    }
};
