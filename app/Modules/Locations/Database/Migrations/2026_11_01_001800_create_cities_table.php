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
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('province_id');
            $table->string('name_fa', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('has_tipax_branch')->default(false);
            $table->boolean('is_delivery_enabled')->default(false);
            $table->timestamp('tipax_synced_at')->nullable();
            $table->timestamps();

            $table->primary(['id']);
            $table->index('is_delivery_enabled');
            $table->index(['province_id', 'name_fa']);
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
