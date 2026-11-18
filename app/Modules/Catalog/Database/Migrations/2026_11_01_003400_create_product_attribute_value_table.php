<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attributes (filterable product details chosen from lists, e.g. dosage form) and tags (nutrient / need shortcuts,
 * e.g. «آهن»), roadmap D3.1. Tag icons are the medialibrary "icon" collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_value', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attribute_value_id');

            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_value');
    }
};
