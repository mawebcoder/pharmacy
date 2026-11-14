<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier price imports from Excel (roadmap D13.9, S16): one row per upload, and its checked rows for the preview
 * (old and new values, the selling price it would give, or why the row cannot be imported). Money in Rial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('status', 16);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_matched')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_import_id')->constrained('price_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('matched_by', 16)->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->boolean('is_available')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->unsignedBigInteger('old_cost')->nullable();
            $table->boolean('old_is_available')->nullable();
            $table->boolean('old_is_enabled')->nullable();
            $table->unsignedBigInteger('selling_price')->nullable();
            $table->boolean('is_new')->default(false);
            $table->boolean('changes')->default(false);
            $table->string('error')->nullable();
            $table->json('raw');

            $table->index(['price_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_import_rows');
        Schema::dropIfExists('price_imports');
    }
};
