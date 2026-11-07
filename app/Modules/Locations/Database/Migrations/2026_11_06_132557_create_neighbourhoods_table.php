<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neighbourhoods with an optional boundary polygon (roadmap D4.2); filled in S24.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighbourhoods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->geometry('polygon', subtype: 'polygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->index(['city_id', 'name_fa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighbourhoods');
    }
};
