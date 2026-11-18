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
        Schema::create('price_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('status', 16);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_matched')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_imports');
    }
};
