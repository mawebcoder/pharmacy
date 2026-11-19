<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a product is for Tipax (roadmap S37, owner 2026-11-19): its package content (a Tipax content id from
 * tipax_references; null means the store default) and whether it is liquid or fragile, per product rather than per SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('tipax_content_id')->nullable()->after('min_shelf_life_months');
            $table->boolean('is_liquid')->default(false)->after('tipax_content_id');
            $table->boolean('is_fragile')->default(false)->after('is_liquid');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['tipax_content_id', 'is_liquid', 'is_fragile']);
        });
    }
};
