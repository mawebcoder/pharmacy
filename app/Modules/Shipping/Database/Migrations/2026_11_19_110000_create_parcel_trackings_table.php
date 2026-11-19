<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last thing Tipax said about an order's parcel (roadmap S37, owner 2026-11-19): its status from BriefTracking,
 * kept for an hour so opening the order page again does not ask Tipax again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_trackings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('tracking_code', 64);
            $table->string('status', 255)->nullable();
            $table->json('data')->nullable();
            // When Tipax was last asked, answered or not.
            $table->timestamp('checked_at');
            // When Tipax last answered.
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_trackings');
    }
};
