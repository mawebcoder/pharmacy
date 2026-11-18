<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-medialibrary's media table (roadmap D8). Preset, status, ThumbHash, colour and Persian alt text
 * live in custom_properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            $table->char('uuid', 36)->nullable();
            $table->string('collection_name', 255);
            $table->string('name', 255);
            $table->string('file_name', 255);
            $table->string('mime_type', 255)->nullable();
            $table->string('disk', 255);
            $table->string('conversions_disk', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();

            $table->unique('uuid');
            $table->index(['model_type', 'model_id']);
            $table->index('order_column');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
