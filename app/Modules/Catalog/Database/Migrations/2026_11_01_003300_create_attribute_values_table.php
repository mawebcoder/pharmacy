<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attributes (filterable product details chosen from lists, e.g. dosage form) and tags (nutrient / need shortcuts,
 * e.g. «آهن»), roadmap D3.1. Tag icons are the medialibrary "icon" collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->string('value_fa', 255);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value_fa']);
            $table->index(['attribute_id', 'sort']);
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
