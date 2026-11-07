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
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa');
            $table->string('code', 64)->unique();
            $table->string('type', 32);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('value_fa');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['attribute_id', 'sort']);
            $table->unique(['attribute_id', 'value_fa']);
        });

        Schema::create('product_attribute_value', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->restrictOnDelete();

            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('type', 32);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_featured', 'sort']);
            $table->index('type');
        });

        Schema::create('product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['product_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('product_attribute_value');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
