<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcategories get their own table (owner, 2026-11-15): categories are one level, each with its subcategories
 * (/categories/{category}/{subcategory}). A product keeps its main category and may name one of its subcategories;
 * extra placements are category_product and the new product_subcategory.
 *
 * Every category below the top level becomes a subcategory of its top-level ancestor, with its products, extra
 * placements, images, banner placements and banner links. Coupon limits on it move to the top-level category; pinned
 * search shortcuts and home widgets drop it (they hold top-level categories only). Then categories lose parent_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name_fa', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'sort']);
            $table->index('is_active');
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcategories');
    }
};
