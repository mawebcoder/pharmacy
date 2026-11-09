<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The banner library and where banners are shown (roadmap D3.6, S13b). Images are medialibrary collections on the
 * banner (desktop, tablet, mobile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('link_type', 32);
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('banner_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->string('place', 32);
            $table->unsignedBigInteger('place_id')->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['place', 'place_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_placements');
        Schema::dropIfExists('banners');
    }
};
