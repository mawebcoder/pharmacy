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
        Schema::create('product_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');

            $table->primary(['product_id', 'tag_id']);
            $table->index('tag_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
    }
};
