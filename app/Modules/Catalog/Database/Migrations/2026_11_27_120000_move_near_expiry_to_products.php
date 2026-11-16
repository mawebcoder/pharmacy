<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «زودمصرف» belongs to the product, not its SKUs (owner, 2026-11-16): one switch in the product form. A product with
 * any SKU flagged keeps the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->after('min_shelf_life_months')->index();
        });

        DB::table('products')
            ->whereIn('id', DB::table('product_variants')->where('is_near_expiry', true)->select('product_id'))
            ->update(['is_near_expiry' => true]);

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex(['is_near_expiry']);
            $table->dropColumn('is_near_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->index();
        });

        DB::table('product_variants')
            ->whereIn('product_id', DB::table('products')->where('is_near_expiry', true)->select('id'))
            ->update(['is_near_expiry' => true]);

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_near_expiry']);
            $table->dropColumn('is_near_expiry');
        });
    }
};
