<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many pieces of a SKU fit one box (roadmap S31, owner 2026-11-18): set on the SKU form for each available box;
 * no row means the SKU does not fit that box. The packer counts each piece as 1/that many of a box.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_box_capacities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('box_id');
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->unique(['variant_id', 'box_id']);
            $table->index('box_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('box_id')->references('id')->on('shipping_boxes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_box_capacities');
    }
};
