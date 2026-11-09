<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home page, built by the admin from widgets in order (roadmap D3.6, S13b). settings holds the type's choices,
 * e.g. {"collection_id": 3} or {"brand_ids": [4, 1, 9]}; slider banners are banner_placements of the widget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('title_fa')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_widgets');
    }
};
