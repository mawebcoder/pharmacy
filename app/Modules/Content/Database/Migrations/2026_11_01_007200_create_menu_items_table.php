<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site menus (roadmap D3.6, S29; owner 2026-11-17): one per location with an optional heading (the footer
 * column's title), and their items in order, each leading to a picked target like a banner (link_type, link_id,
 * external_url) with an optional heroicon. MenusSeeder fills them with the site's starting links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('menu_id');
            $table->string('title', 60);
            $table->string('icon', 40)->nullable();
            $table->string('link_type', 32);
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'is_active', 'sort']);
            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
