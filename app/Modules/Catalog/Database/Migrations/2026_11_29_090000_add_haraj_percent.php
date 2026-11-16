<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Haraj is a percentage, not a fixed price (owner, 2026-11-17): selling prices follow supplier costs and commissions,
 * so the pricing engine works the haraj price out of the percentage at every recalculation. Set on the product and
 * inherited by its SKUs; a SKU's own percentage overrides it (0 = no haraj on that SKU). sale_price stays as the
 * engine's result. Existing haraj prices become their current percentage, so nothing changes for customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('haraj_percent', 5, 2)->nullable()->after('commission_percent');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('haraj_percent', 5, 2)->nullable()->after('sale_price');
        });

        DB::table('product_variants')->whereNotNull('sale_price')->where('price', '>', 0)->orderBy('id')->chunkById(500, function ($variants): void {
            foreach ($variants as $variant) {
                DB::table('product_variants')->where('id', $variant->id)->update([
                    'haraj_percent' => round((1 - (int) $variant->sale_price / (int) $variant->price) * 100, 2),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('haraj_percent');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('haraj_percent');
        });
    }
};
