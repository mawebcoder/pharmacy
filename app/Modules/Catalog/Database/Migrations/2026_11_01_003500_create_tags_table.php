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
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('slug', 255);
            $table->string('type', 32);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index(['is_featured', 'sort']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
