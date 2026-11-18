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
        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('official_price')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('provider_cost')->nullable();
            $table->string('provider_choice', 24)->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->string('commission_source', 24)->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->boolean('is_near_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            $table->string('reason', 32);
            $table->json('flags')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'id']);
            $table->foreign('changed_by')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
    }
};
