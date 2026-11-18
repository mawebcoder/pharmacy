<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products (roadmap D3.1, D12.2). Sellable units are the variants; photos are the medialibrary "gallery" collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
