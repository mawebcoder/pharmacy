<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's brand, category and subcategory may be empty (owner, 2026-11-27): the catalog import brings only the
 * basic data and staff set them in the admin, where they stay required. A product without them cannot be active, so
 * the shop never shows it. MySQL cannot change a column used by a foreign key: the keys are dropped and added again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['primary_category_id']);
            $table->dropForeign(['primary_subcategory_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('brand_id')->nullable()->change();
            $table->unsignedBigInteger('primary_category_id')->nullable()->change();
            $table->unsignedBigInteger('primary_subcategory_id')->nullable()->change();

            $table->foreign('brand_id')->references('id')->on('brands')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_category_id')->references('id')->on('categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_subcategory_id')->references('id')->on('subcategories')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['primary_category_id']);
            $table->dropForeign(['primary_subcategory_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
            $table->unsignedBigInteger('primary_category_id')->nullable(false)->change();
            $table->unsignedBigInteger('primary_subcategory_id')->nullable(false)->change();

            $table->foreign('brand_id')->references('id')->on('brands')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_category_id')->references('id')->on('categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('primary_subcategory_id')->references('id')->on('subcategories')->restrictOnDelete()->cascadeOnUpdate();
        });
    }
};
