<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers, what each one charges per SKU with its change history, and the SKU price records (roadmap D3.2, S15,
 * S16). Money is integer Rial. Every existing SKU gets an «initial» record of its current prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('cost');
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_near_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'variant_id']);
            $table->index(['variant_id', 'is_enabled', 'is_available', 'cost']);
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_prices');
    }
};
