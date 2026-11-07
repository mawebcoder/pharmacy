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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('primary_category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('product_type', 32);
            $table->boolean('requires_prescription')->default(false);
            $table->string('irc_code', 32)->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('usage')->nullable();
            $table->text('warnings')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('interactions')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'product_type']);
            $table->fullText(['name_fa', 'name_en']);
        });

        // Extra categories; the primary one is products.primary_category_id.
        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('product_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort']);
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_faqs');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};
