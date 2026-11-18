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
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('contact_name', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
