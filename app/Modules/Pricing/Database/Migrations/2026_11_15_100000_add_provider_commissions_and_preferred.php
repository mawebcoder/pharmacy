<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission per supplier and the preferred supplier (roadmap D3.2, S15b): on a SKU's supplier row, per product and
 * supplier, and per brand and supplier. Price records keep which level the commission came from and how the supplier
 * was chosen; import rows carry the two new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_prices', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('cost');
            $table->boolean('is_preferred')->default(false)->after('is_enabled');
        });

        Schema::create('product_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'provider_id']);
        });

        Schema::create('brand_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->decimal('commission_percent', 5, 2);
            $table->timestamps();

            $table->unique(['brand_id', 'provider_id']);
        });

        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->string('provider_choice', 24)->nullable()->after('provider_cost');
            $table->string('commission_source', 24)->nullable()->change();
        });

        Schema::table('price_import_rows', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('lead_time_hours');
            $table->boolean('is_preferred')->nullable()->after('commission_percent');
            $table->decimal('old_commission_percent', 5, 2)->nullable()->after('old_is_enabled');
            $table->boolean('old_is_preferred')->nullable()->after('old_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('price_import_rows', function (Blueprint $table): void {
            $table->dropColumn(['commission_percent', 'is_preferred', 'old_commission_percent', 'old_is_preferred']);
        });

        Schema::table('variant_prices', function (Blueprint $table): void {
            $table->dropColumn('provider_choice');
            $table->string('commission_source', 16)->nullable()->change();
        });

        Schema::dropIfExists('brand_providers');
        Schema::dropIfExists('product_providers');

        Schema::table('provider_prices', function (Blueprint $table): void {
            $table->dropColumn(['commission_percent', 'is_preferred']);
        });
    }
};
