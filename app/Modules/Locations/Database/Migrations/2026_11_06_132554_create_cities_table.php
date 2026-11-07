<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cities (roadmap D4.2). Coordinates are the county centre from GeoNames when known; delivery is switched on by admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('county_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->string('slug');
            $table->string('type', 32)->default('city');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_delivery_enabled')->default(false);
            $table->unsignedBigInteger('source_id')->unique();
            $table->timestamps();

            $table->index('is_delivery_enabled');
            $table->index(['province_id', 'name_fa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
