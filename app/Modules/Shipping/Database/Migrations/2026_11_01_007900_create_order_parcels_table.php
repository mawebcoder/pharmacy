<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a paid order is packed (roadmap S31, owner 2026-11-17): one row per parcel, in order, with the box as it was
 * (name, sizes, own weight; no box when nothing fits), the items in it, their weight, the parcel's total weight, the
 * Tipax item type (general, or liquid-fragile when any item is) and whether defaults stood in for missing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_parcels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedSmallInteger('sequence');
            $table->unsignedBigInteger('box_id')->nullable();
            $table->json('box_snapshot')->nullable();
            $table->json('items');
            $table->unsignedInteger('items_weight_g');
            $table->unsignedInteger('total_weight_g');
            $table->unsignedBigInteger('declared_value');
            $table->string('content_type', 16);
            $table->boolean('is_estimate')->default(false);
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_parcels');
    }
};
