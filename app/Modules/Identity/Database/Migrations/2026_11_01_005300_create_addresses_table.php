<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers' delivery addresses (roadmap D3.3, S20). No map pin (owner, 2026-11-14): the postal code and the full
 * address are enough. Soft deleted, because orders keep the id next to their address snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('title', 40);
            $table->string('receiver_name', 80);
            $table->string('receiver_mobile', 11);
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('city_id');
            $table->string('main_street', 120)->nullable();
            $table->string('side_street', 120)->nullable();
            $table->string('alley', 120)->nullable();
            $table->string('block', 20)->nullable();
            $table->string('full_address', 500)->nullable();
            $table->string('plaque', 10);
            $table->string('unit', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->char('postal_code', 10);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_default']);
            $table->foreign('city_id')->references('id')->on('cities')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
