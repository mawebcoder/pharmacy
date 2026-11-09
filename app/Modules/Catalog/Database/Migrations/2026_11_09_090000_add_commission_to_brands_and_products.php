<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission % (roadmap D3.2, S15): the brand's applies to its products; a product's own overrides it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('commission_percent');
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('commission_percent');
        });
    }
};
