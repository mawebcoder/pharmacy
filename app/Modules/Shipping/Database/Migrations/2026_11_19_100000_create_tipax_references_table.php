<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipax's own lists (roadmap D11.3 step 7, S33): services, pack types, packings and package contents, by Tipax's ids,
 * for the admin's dropdowns. Filled by `tipax:sync-reference`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipax_references', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->unsignedInteger('external_id');
            $table->string('title', 255);
            // The pack type a packing or content belongs to (Tipax refuses mismatches).
            $table->unsignedInteger('pack_type')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'external_id']);
            $table->index(['type', 'pack_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipax_references');
    }
};
