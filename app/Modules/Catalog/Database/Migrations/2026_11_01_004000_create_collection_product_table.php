<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collections: hand-picked product lists in the admin's order (roadmap D3.1, S14). «محصولات محبوب» (slug popular)
 * is the system collection the empty search box shows; it is created here so every environment has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_product', function (Blueprint $table): void {
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('sort')->default(0);

            $table->primary(['collection_id', 'product_id']);
            $table->index('product_id');
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_product');
    }
};
