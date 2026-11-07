<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SKU the admin wants shown and added to the cart (products.default_variant_id) and the admin's order of the
 * variants, which decides the next SKU when the default is out of stock (S12, decided 2026-11-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort')->default(0)->after('title_fa');
            $table->index(['product_id', 'sort']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('default_variant_id')->nullable()->after('primary_category_id')
                ->constrained('product_variants')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_variant_id');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'sort']);
            $table->dropColumn('sort');
        });
    }
};
