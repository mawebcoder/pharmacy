<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers, what each one charges per SKU with its change history, and the SKU price records (roadmap D3.2, S15,
 * S16). Money is integer Rial. Every existing SKU gets an «initial» record of its current prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_price_changes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('cost')->nullable();
            $table->boolean('is_available')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->string('event', 16);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
            $table->foreign('changed_by')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_price_changes');
    }
};
