<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * City regions such as Tehran's 22 districts (roadmap D4.2); filled in S24 for own-courier zones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->unsignedSmallInteger('number')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
