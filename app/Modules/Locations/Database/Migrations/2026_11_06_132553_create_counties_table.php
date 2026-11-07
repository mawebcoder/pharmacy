<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counties (شهرستان) of each province (roadmap D4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->string('slug');
            $table->unsignedBigInteger('source_id')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};
