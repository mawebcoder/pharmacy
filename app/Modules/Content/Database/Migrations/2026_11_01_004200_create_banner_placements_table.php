<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The banner library and where banners are shown (roadmap D3.6, S14). Images are medialibrary collections on the
 * banner (desktop, tablet, mobile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_placements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('banner_id');
            $table->string('place', 32);
            $table->unsignedBigInteger('place_id')->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['place', 'place_id', 'sort']);
            $table->foreign('banner_id')->references('id')->on('banners')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_placements');
    }
};
