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
        Schema::create('product_subcategory', function (Blueprint $table): void {
            $table->unsignedBigInteger('subcategory_id');
            $table->unsignedBigInteger('product_id');

            $table->primary(['subcategory_id', 'product_id']);
            $table->index('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('subcategory_id')->references('id')->on('subcategories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subcategory');
    }
};
