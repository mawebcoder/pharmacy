<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flash deal an order item was bought under (roadmap S33): its units are counted against the deal when the order
 * is placed and given back when the order expires unpaid or is cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('flash_deal_id')->nullable()->after('variant_price_id')->constrained('flash_deals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('flash_deal_id');
        });
    }
};
