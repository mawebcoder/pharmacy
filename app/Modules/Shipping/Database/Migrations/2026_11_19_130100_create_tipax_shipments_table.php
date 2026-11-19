<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order registered with Tipax (roadmap S35, owner 2026-11-19): the parcels going out, or coming back as a return;
 * Tipax's correlation id and tracking codes, what was sent and answered, and its callback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipax_shipments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('correlation_id', 64)->nullable();
            $table->json('tracking_codes')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->json('callback')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'kind']);
            $table->index('correlation_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipax_shipments');
    }
};
