<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission per supplier and the preferred supplier (roadmap D3.2, S17): on a SKU's supplier row, per product and
 * supplier, and per brand and supplier. Price records keep which level the commission came from and how the supplier
 * was chosen; import rows carry the two new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_providers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('provider_id');
            $table->decimal('commission_percent', 5, 2);
            $table->timestamps();

            $table->unique(['brand_id', 'provider_id']);
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_providers');
    }
};
