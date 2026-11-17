<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «زودمصرف» follows the supplier the site sells from (owner, 2026-11-17): the SKU copies the selling supplier's flag
 * and expiry date (written by the pricing engine, or by a live flash deal's supplier). The product's own switch is
 * removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->after('flash_deal_id')->index();
            $table->date('expiry_date')->nullable()->after('is_near_expiry');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_near_expiry']);
            $table->dropColumn('is_near_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_near_expiry')->default(false)->index();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex(['is_near_expiry']);
            $table->dropColumn(['is_near_expiry', 'expiry_date']);
        });
    }
};
