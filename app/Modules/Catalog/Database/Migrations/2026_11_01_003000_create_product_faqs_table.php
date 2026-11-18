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
        Schema::create('product_faqs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_faqs');
    }
};
