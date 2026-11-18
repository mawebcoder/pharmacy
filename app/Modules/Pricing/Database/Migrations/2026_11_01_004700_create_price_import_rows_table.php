<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier price imports from Excel (roadmap D13.9, S18): one row per upload, and its checked rows for the preview
 * (old and new values, the selling price it would give, or why the row cannot be imported). Money in Rial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('price_import_id');
            $table->unsignedInteger('row_number');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('matched_by', 16)->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->boolean('is_available')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->boolean('is_preferred')->nullable();
            $table->unsignedBigInteger('old_cost')->nullable();
            $table->boolean('old_is_available')->nullable();
            $table->boolean('old_is_enabled')->nullable();
            $table->decimal('old_commission_percent', 5, 2)->nullable();
            $table->boolean('old_is_preferred')->nullable();
            $table->unsignedBigInteger('selling_price')->nullable();
            $table->boolean('is_new')->default(false);
            $table->boolean('changes')->default(false);
            $table->string('error', 255)->nullable();
            $table->json('raw');

            $table->index(['price_import_id', 'row_number']);
            $table->foreign('price_import_id')->references('id')->on('price_imports')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_import_rows');
    }
};
