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
        Schema::create('product_relations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'type']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relations');
    }
};
