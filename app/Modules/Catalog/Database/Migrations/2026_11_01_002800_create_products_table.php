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
            $table->string('name_fa', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('slug', 255);
            $table->unsignedBigInteger('brand_id');
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('haraj_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('primary_category_id');
            $table->unsignedBigInteger('primary_subcategory_id');
            $table->unsignedBigInteger('default_variant_id')->nullable();
            $table->string('product_type', 32);
            $table->unsignedSmallInteger('max_per_order')->nullable();
            $table->unsignedTinyInteger('min_shelf_life_months')->nullable();
            $table->string('irc_code', 32)->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('usage')->nullable();
            $table->text('warnings')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('interactions')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index(['status', 'product_type']);
            $table->fullText(['name_fa', 'name_en']);
            $table->foreign('brand_id')->references('id')->on('brands')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('default_variant_id')->references('id')->on('product_variants')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_category_id')->references('id')->on('categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_subcategory_id')->references('id')->on('subcategories')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
